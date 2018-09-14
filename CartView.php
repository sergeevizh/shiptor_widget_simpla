<?PHP

/**
 * Simpla CMS
 *
 * @copyright 	2009 Denis Pikusov
 * @link 		http://simp.la
 * @author 		Denis Pikusov
 *
 * Корзина покупок
 * Этот класс использует шаблон cart.tpl
 *
 */

require_once('View.php');
require_once('Checkoutru.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/api/Features.php');

class CartView extends View
{

  //////////////////////////////////////////
  // Изменения товаров в корзине
  //////////////////////////////////////////
  public function __construct()
  {
	parent::__construct();

    // Если передан id варианта, добавим его в корзину
    if($variant_id = $this->request->get('variant', 'integer'))
    {
		$this->cart->add_item($variant_id, $this->request->get('amount', 'integer'));
	    header('location: '.$this->config->root_url.'/cart/');

    }

    // Удаление товара из корзины
    if($delete_variant_id = intval($this->request->get('delete_variant')))
    {
      $this->cart->delete_item($delete_variant_id);
      if(!isset($_POST['submit_order']) || $_POST['submit_order']!=1)
			header('location: '.$this->config->root_url.'/cart/');
		}

    // Если нажали оформить заказ
    if(isset($_POST['checkout']))
    {

        $Shiptor          = json_decode($this->request->post('shiptor'), true);

    	$order = new stdClass;
    	$order->delivery_id = $this->request->post('delivery_id', 'integer');
    	$order->name        = $this->request->post('name');
    	$order->email       = $this->request->post('email');
    	//$order->address     = $this->request->post('address');
	// $order->address = '';
        $order->phone       = $this->request->post('phone');
    	$order->comment     = $this->request->post('comment');
    	$order->ip      	= $_SERVER['REMOTE_ADDR'];
        $Shiptor          = json_decode($this->request->post('shiptor'), true);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/shiptor.log', var_export($Shiptor , true)  );

		$this->design->assign('delivery_id', $order->delivery_id);
		$this->design->assign('name', $order->name);
		$this->design->assign('email', $order->email);
		$this->design->assign('phone', $order->phone);
		// $this->design->assign('address', $order->address);

    	// $captcha_code =  $this->request->post('captcha_code', 'string');

		// Скидка
		$cart = $this->cart->get_cart();
		$order->discount = $cart->discount;

		if($cart->coupon)
		{
			$order->coupon_discount = $cart->coupon_discount;
			$order->coupon_code = $cart->coupon->code;
		}

		//echo $order->discount."----";echo $order->coupon_discount."----";die();


		//

    	if(!empty($this->user->id))
	    	$order->user_id = $this->user->id;

    	if(empty($order->name))
    	{
    		$this->design->assign('error', 'empty_name');
    	}
    	elseif(empty($order->email))
    	{
    		$this->design->assign('error', 'empty_email');
    	}
//    	elseif($_SESSION['captcha_code'] != $captcha_code || empty($captcha_code))
//    	{
//    		$this->design->assign('error', 'captcha');
//    	}  
    	else 
    	{
	    	// Добавляем заказ в базу

				if(isset($_POST['city']) && $_POST['city']) {
					$order->address = $_POST['city'].', '.$order->address;
				}
				if(isset($_POST['house']) && $_POST['house']) {
					$order->address = $order->address.', дом '.$_POST['house'];
				}
				if(isset($_POST['appartament']) && $_POST['appartament']) {
					$order->address = $order->address.', кв '.$_POST['appartament'];
				}
				if(isset($_POST['postindex']) && $_POST['postindex']) {
					$order->address = $order->address.', '.$_POST['postindex'];
				}

	    	$order_id = $this->orders->add_order($order);

	    	$_SESSION['order_id'] = $order_id;

	    	// Если использовали купон, увеличим количество его использований
	    	if($cart->coupon)
	    		$this->coupons->update_coupon($cart->coupon->id, array('usages'=>$cart->coupon->usages+1));

	    	// Добавляем товары к заказу
	    	foreach($this->request->post('amounts') as $variant_id=>$amount)
	    	{
	    		$this->orders->add_purchase(array('order_id'=>$order_id, 'variant_id'=>intval($variant_id), 'amount'=>intval($amount)));
	    	}
	    	$order = $this->orders->get_order($order_id);

				// CHECKOUT //
				$types = array(7=>'express', 8=>'mail',5=>'pvz',6=>'postamat');
				$address = $order->address;



				if(isset($types[$_POST['delivery_id']])) {

					$amonts = $this->request->post('amounts');
					foreach($cart->purchases as $good) {

						$weight = $good->product->weight;
						// if(!$weight) {
						// 	$product_features = $features->get_product_options($good->product->id);
						// 	foreach($product_features as $feature)	{
						// 		if($feature->name == 'Вес' && $feature->value){
						// 			$weight = $feature->value;
						// 		}
						// 	}
						// }

						$goods[] = array('name' => $good->product->name,
							'code' => $good->variant->sku,
							'variantCode' => '',
							'quantity' => $amonts[$good->variant->id],
							'assessedCost' => $good->variant->price,
							'payCost' => $good->variant->price,
							'weight' => ($weight)?$weight:0
						);
					}

					if($order->coupon_discount > 0) {
						$goods[] = array('name' => 'Скидка',
							'code' => 'Скидка',
							'variantCode' => '',
							'quantity' => 1,
							'assessedCost' => '-'.$order->coupon_discount,
							'payCost' => '-'.$order->coupon_discount,
							'weight' => 0
						);
					}

					$payment_type = 'cash';
					$request = array('goods' => $goods,
									'delivery' => array(
										'deliveryId' => $_POST['checkout_delivery_id'],
										'placeFiasId' => $_POST['id_place_fias'],
										'addressExpress' => array(
											'postindex' =>  $_POST['postindex'],
											'streetFiasId' => $_POST['id_street_fias'],
											'house' => 			$_POST['house'],
											'appartment' => $_POST['appartament']
										),
										'addressPvz' => $_POST['address_'.$types[$_POST['delivery_id']]],
										'type' => $types[$_POST['delivery_id']],
										'cost' => $_POST['delivery_cost'],
										'minTerm' => $_POST['min_term'],
										'maxTerm' => $_POST['max_term']
									),
									'user' => array(
										'fullname' => $_POST['name'],
										'email' => $_POST['email'],
										'phone' => $_POST['phone']
									),
									'comment' => $_POST['comment'],
									'shopOrderId' => $order_id."_simpla",
									'paymentMethod' => $payment_type
								);

					$checkout = new Checkoutru();
					$response_json = $checkout->createOrder($request);
					$response = json_decode($response_json, true);

					$address = '';
					if($types[$_POST['delivery_id']]=='pvz' || $types[$_POST['delivery_id']]=='postamat') {
						$address .= $_POST['city'];
						if($_POST['address_'.$types[$_POST['delivery_id']]]!='') $address .= ', '.$_POST['address_'.$types[$_POST['delivery_id']]];
					} else {
						$address .= $_POST['city'];
						if($_POST['address']!='') $address .= ', '.$_POST['address'];
						if($_POST['house']!='') $address .= ', д. '.$_POST['house'];
						if($_POST['appartament']!='') $address .= ', кв. '.$_POST['appartament'];
						if($_POST['postindex']!='') $address .= ', '.$_POST['postindex'];
					}
				}

	    	// Стоимость доставки
				$delivery = $this->delivery->get_delivery($order->delivery_id);
				if(isset($types[$_POST['delivery_id']])) {$delivery->price = $_POST['delivery_cost'];}
	    	
                    if(isset($Shiptor['pvz'])){
                        //array_reverse(sSiptor['pvz']['address']);
                        $this->orders->update_order($order->id, array(
                                'delivery_price' => str_replace(' руб.' , '' , $Shiptor['pvz']['cost']), 
                                //'separate_delivery'=>$delivery->separate_payment, 
                                'address' =>  $Shiptor['pvz']['address']
                            )
                        );
                    } elseif(isset($Shiptor['courier'])){                        
                        //array_reverse(sSiptor['contacts']['address']);
                        $this->orders->update_order($order->id, array(
                                'delivery_price' => $Shiptor['courier']['cost']['total']['sum'], 
                                //'separate_delivery'=>$delivery->separate_payment,                                 
                                'address' => $Shiptor['location']['city'] . ' , ' . $Shiptor['street'] . ' , ' . $Shiptor['dom']
                            )
                        );                        
                    }  elseif(isset($Shiptor['pochta'])){  
                        
                            $this->orders->update_order($order->id, array(
                                'delivery_price' => $Shiptor['pochta']['cost']['total']['sum'], 
                                //'separate_delivery'=>$delivery->separate_payment,                                 
                                'address' => $Shiptor['location']['city'] . ' , ' . $Shiptor['street'] . ' , ' . $Shiptor['dom']
                            )
                        );  
                    } 

                // if(!empty($delivery) && $delivery->free_from > $order->total_price)
	    	// {
	    		//$this->orders->update_order($order->id, array('delivery_price'=>$delivery->price, 'separate_delivery'=>$delivery->separate_payment, 'address'=>$address));
	    	// }
	    	
	    	                   if(isset($Shiptor['pvz'])){
                        //array_reverse(sSiptor['pvz']['address']);
                        $this->orders->update_order($order->id, array(
                                'delivery_price' => str_replace(' руб.' , '' , $Shiptor['pvz']['cost']), 
                                //'separate_delivery'=>$delivery->separate_payment, 
                                'address' =>  $Shiptor['pvz']['address']
                            )
                        );
                    } elseif(isset($Shiptor['courier'])){                        
                        //array_reverse(sSiptor['contacts']['address']);
                        $this->orders->update_order($order->id, array(
                                'delivery_price' => $Shiptor['courier']['cost']['total']['sum'], 
                                //'separate_delivery'=>$delivery->separate_payment,                                 
                                'address' => $Shiptor['location']['city'] . ' , ' . $Shiptor['street'] . ' , ' . $Shiptor['dom']
                            )
                        );                        
                    }  elseif(isset($Shiptor['pochta'])){  
                        
                            $this->orders->update_order($order->id, array(
                                'delivery_price' => $Shiptor['pochta']['cost']['total']['sum'], 
                                //'separate_delivery'=>$delivery->separate_payment,                                 
                                'address' => $Shiptor['location']['city'] . ' , ' . $Shiptor['street'] . ' , ' . $Shiptor['dom']
                            )
                        );  
                    }

			// Отправляем письмо пользователю
			$this->notify->email_order_user($order->id);

			// Отправляем письмо администратору
			$this->notify->email_order_admin($order->id);

	    	// Очищаем корзину (сессию)
			$this->cart->empty_cart();

			// Перенаправляем на страницу заказа
			header('Location: '.$this->config->root_url.'/order/'.$order->url);
		}
    }
    else
    {

	    // Если нам запостили amounts, обновляем их
	    if($amounts = $this->request->post('amounts'))
	    {
			foreach($amounts as $variant_id=>$amount)
			{
				$this->cart->update_item($variant_id, $amount);
			}

	    	$coupon_code = trim($this->request->post('coupon_code', 'string'));
	    	if(empty($coupon_code))
	    	{
	    		$this->cart->apply_coupon('');
				header('location: '.$this->config->root_url.'/cart/');
	    	}
	    	else
	    	{
				$coupon = $this->coupons->get_coupon((string)$coupon_code);

				if(empty($coupon) || !$coupon->valid)
				{
		    		$this->cart->apply_coupon($coupon_code);
					$this->design->assign('coupon_error', 'invalid');
				}
				else
				{
					$this->cart->apply_coupon($coupon_code);
					header('location: '.$this->config->root_url.'/cart/');
				}
	    	}
		}

	}

  }


	//////////////////////////////////////////
	// Основная функция
	//////////////////////////////////////////
	function fetch()
	{
		// Способы доставки
		$deliveries = $this->delivery->get_deliveries(array('enabled'=>1));
		$this->design->assign('deliveries', $deliveries);

		// Данные пользователя
		if($this->user)
		{
			$last_order = $this->orders->get_orders(array('user_id'=>$this->user->id, 'limit'=>1));
			$last_order = reset($last_order);
			if($last_order)
			{
				$this->design->assign('name', $last_order->name);
				$this->design->assign('email', $last_order->email);
				$this->design->assign('phone', $last_order->phone);
				$this->design->assign('address', $last_order->address);

			}
			else
			{
				$this->design->assign('name', $this->user->name);
				$this->design->assign('email', $this->user->email);
			}
		}

		// Если существуют валидные купоны, нужно вывести инпут для купона
		if($this->coupons->count_coupons(array('valid'=>1))>0)
			$this->design->assign('coupon_request', true);

		// Checkout //
		if(isset($_POST)) {
			$this->design->assign('postindex', $_POST['postindex']);
			$this->design->assign('city', $_POST['city']);
			$this->design->assign('house', $_POST['house']);
			$this->design->assign('appartament', $_POST['appartament']);
			$this->design->assign('max_term', $_POST['max_term']);
			$this->design->assign('min_term', $_POST['min_term']);
			$this->design->assign('delivery_cost', $_POST['delivery_cost']);
			$this->design->assign('id_street_fias', $_POST['id_street_fias']);
			$this->design->assign('id_place_fias', $_POST['id_place_fias']);
			$this->design->assign('selected_punkt', $_POST['selected_punkt']);
			$this->design->assign('address_pvz', $_POST['address_pvz']);
			$this->design->assign('checkout_delivery_id', $_POST['checkout_delivery_id']);
		}

		// Выводим корзину
		return $this->design->fetch('cart.tpl');
	}

}
