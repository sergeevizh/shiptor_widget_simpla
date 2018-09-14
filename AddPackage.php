<?php
use ShiptorRussiaApiClient\Client\Shiptor,
    ShiptorRussiaApiClient\Client\Core\Response\ErrorResponse;

require_once $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
 
$apikey = file_get_contents('config/shiptor_api_key.txt');
if(empty($apikey)) {
    echo json_encode(['error' => 'empty api key!']); 
    return;
}
$shiptor = new Shiptor(["API_KEY" => $apikey]);
  
$inputs = filter_input_array(INPUT_POST);
file_put_contents('shiptor_inputs.log' , var_export($inputs, true));

$package = $shiptor->ShippingEndpoint()->addPackage();
$request = $package
->setLength(null !== array_sum($inputs['length']) ? array_sum($inputs['length']) : 10)
->setWidth(null !== array_sum($inputs['width']) ? array_sum($inputs['width']) : 10)
->setHeight(10)
->setWeight(null !== array_sum($inputs['weight']) ? array_sum($inputs['weight']) : 10)
->setCod(0)
        
        // Оценоч стоимость
->setDeclaredCost(  ($cost = array_sum(array_column($inputs['products'] , 'price'))) > 0 ? $cost : 1000) // Оценочная стоимо
->setExternalId("123456") // Идентификатор
        // Метод
->setShippingMethod( isset($inputs['courier']) ? $inputs['courier']['method']['id'] : ( isset($inputs['pvz']) ? $inputs['pvz']['shipping_methods'][0] : '20' ) )  // Метод доставки
->setComment( $inputs['comment'] )
//->toMoscow()
        // Кладр
->setKladrId(  $inputs['location']['kladr_id']  ) // Код КЛАДР населенного пункта
->setName( $inputs['name'] )
->setSurname(".")
->setPatronimic("")
->setEmail($inputs['email'])
->setPhone($inputs['phone'])
   // город
  //      ->setRegion($inputs['location']['city'])
//->setSettlement($inputs['location']['city'])
        ->setApartment(  isset($inputs['flat']) ? $inputs['flat'] : '' )
        ->setHouse( isset($inputs['dom']) ? $inputs['dom'] : '' )
        ->setStreet( isset($inputs['street']) ? $inputs['street'] : '' )
->setAddressLine(    isset($inputs['pvz']['prepare_address']) ? $inputs['pvz']['prepare_address']['street']  :  $inputs['street'] 
        . '  ' . $inputs['dom'] . '  ' .  $inputs['flat']) 
->forRu()
        //->setDeliverypoint
//->setPostalCode("101000");
        //->setDeliveryPoint(isset($inputs['pvz']['id']) ? $inputs['pvz']['id'] : '' )
;

if(isset($inputs['pvz'])){
    $package->setDeliveryPoint($inputs['pvz']['id']);
}


foreach($inputs['products'] as $prod){
    //$shiptor->ShippingEndpoint()->addProduct()->setName($prod['name'])->setArticle(123)->setPrice($prod['price']);
    $request->newProduct()->setShopArticle($prod['name'])->setCount($prod['count'])->setPrice($prod['price']);
    //$request->newProduct()->setShopArticle("153")->setCount(1)->setPrice(2200);
    //$request->newService()->setShopArticle("SD28346")->setCount(1)->setPrice(300);
}

$response = $request->send();

if($response instanceof ErrorResponse):?>
    <?php echo json_encode(['error' => $response->getMessage()])?>
<?php else:?>
        <?php
        $result = $response->getResult();
	echo json_encode(['success' => $result->getId()]);
        die();
        ?>
    <ul>    
        <li><?php echo $result->getId()?>#<?php echo $result->getExternalId()?></li>
        <li><?php echo $result->getStatus()?> <?php echo $result->getCreateDate()?></li>
        <li><?php echo $result->getWeight()?></li>
        <li><?php echo $result->getLength()?></li>
        <li><?php echo $result->getWidth()?></li>
        <li><?php echo $result->getHeight()?></li>
        <li><?php echo $result->getCod()?></li>
        <li><?php echo $result->getDeclaredCost()?></li>
        <li><?php echo $result->getDeliveryTime()?></li>
        <li><?php echo $result->getTrackingNumber()?></li>
        <li><?php echo $result->getDelayedDeliveryAt()?></li>
        <li><?php echo $result->getLabel()?></li>
        <li><?php echo $result->getPickup()?></li>
        <?php if($result->hasPhotos()):?>
            <li>
                <ol>
                    <?php foreach($result->getPhotos() as $photo):?>
                        <li><a href="<?php echo $photo->getMedium()?>" target="_blank"><img src="<?php echo $photo->getMini()?>"></a></li>
                    <?php endforeach?>
                </ol>
            </li>
        <?php endif;
        $departure = $result->getDeparture();
        ?>
        <?php if($departure->hasDeliveryPoint()):
            $deliveryPoint = $departure->getDeliveryPoint();
            ?>
            <li>#<?php echo $deliveryPoint->getId()?> <?php $deliveryPoint->getAddress()?></li>
            <li><?php echo $deliveryPoint->getCourier()?></li>
            <li><?php echo $deliveryPoint->getAddress()?></li>
            <li><?php echo $deliveryPoint->getPhones()?></li>
            <li><?php echo $deliveryPoint->getDescription()?></li>
            <li><?php echo $deliveryPoint->getSchedule()?></li>
            <li><?php echo $deliveryPoint->getShippingDays()?></li>
            <li><?php echo $deliveryPoint->getCod()?></li>
            <li><?php echo $deliveryPoint->getLongitude()?> <?php echo $deliveryPoint->getLatitude()?></li>
            <li><?php echo $deliveryPoint->getKladrId()?></li>
            <li><?php echo implode(",",$deliveryPoint->getShippingMethods())?></li>
        <?php endif?>
        <li><?php echo $departure->getCashlessPayment()?></li>
        <li><?php echo $departure->getComment()?></li>
        <?php
        $shippingMethod = $departure->getShippigmethod();
        ?>
        <li><?php echo $shippingMethod->getId()?> <?php echo $shippingMethod->getName()?></li>
        <li><?php echo $shippingMethod->getCategory()?></li>
        <li><?php echo $shippingMethod->getGroup()?></li>
        <li><?php echo $shippingMethod->getCourier()?></li>
        <li><?php echo $shippingMethod->getComment()?></li>
        <li><?php echo $shippingMethod->getDescription()?></li>
        <?php
        $address = $departure->getAddress();
        ?>
        <li><?php echo $address->getReciever()?></li>
        <li><?php echo $address->getName()?> <?php echo $address->getPatronymic()?> <?php echo $address->getSurname()?></li>
        <li><?php echo $address->getEmail()?></li>
        <li><?php echo $address->getPhone()?></li>
        <li><?php echo $address->getCountryCode()?></li>
        <li><?php echo $address->getPostCode()?></li>
        <li><?php echo $address->getRegion()?></li>
        <li><?php echo $address->getSettlement()?></li>
        <li><?php echo $address->getStreet()?></li>
        <li><?php echo $address->getHouse()?></li>
        <li><?php echo $address->getApartment()?></li>
        <li><?php echo $address->getKladrId()?></li>
    </ul>
<?php
endif;
?>
