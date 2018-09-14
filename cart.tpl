
<script>
     window.products = [ 
{foreach from=$cart->purchases item=product}
        { 
            //id: '{$product->product->id}',
            name: '{$product->product->name|escape:html}',
            price: {$product->variant->price} ,
            count: {($product->amount > 0) ? $product->amount : 1}  ,
        },
{/foreach}
    ];
    
    window.weight = [
        {foreach from=$cart->purchases item=product}
            {($product->weight > 0) ? $product->weight : 3},
        {/foreach}
    ]
    
    window.length = [
        {foreach from=$cart->purchases item=product}
            {($product->length > 0) ? $product->length : 10},
        {/foreach}
    ]
    
    window.width = [
        {foreach from=$cart->purchases item=product}
            {($product->width > 0) ? $product->width : 10} ,
        {/foreach}
    ]
    
</script>


<script type="text/javascript" src="https://widget.shiptor.ru/embed/widget.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="/shiptor.js"></script>

    <style>
        ._shiptor_widget_group{
            position: relative;
        }
        ._shiptor_widget_settlement_suggestions{
            position: absolute;
            z-index: 1;
            height: 0;
            top: 70px;
            width: 100%;
        }
        .additional_inputs{
            z-index: 1;
        }
    </style>