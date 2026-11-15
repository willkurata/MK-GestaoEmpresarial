<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site público MK Negócios
    |--------------------------------------------------------------------------
    |
    | O site institucional e imobiliário vive isolado do MK Gestão: rotas em
    | routes/site.php, controllers em App\Http\Controllers\Site, páginas em
    | resources/js/pages/site e root view resources/views/site.blade.php.
    |
    | Com SITE_DOMAIN definido (ex.: mknegocios.com.br), o site responde na
    | raiz desse domínio. Sem ele (ambiente local), o site fica em /site para
    | não conflitar com o login do MK Gestão em "/".
    |
    */

    'domain' => env('SITE_DOMAIN'),

    'prefix' => env('SITE_DOMAIN') ? '' : 'site',

    /*
    | Endereço público do MK Gestão (ex.: https://app.mknegocios.com.br),
    | usado no link "Área do cliente". Sem valor, aponta para "/".
    */
    'gestao_url' => env('MK_GESTAO_URL', '/'),

    'contact' => [
        'whatsapp' => '5512997298895',
        'whatsapp_display' => '(12) 99729-8895',
        'email' => 'contato@mknegocios.com',
    ],

    'cities' => ['Ubatuba', 'Caraguatatuba'],

    /*
    | Registros profissionais exibidos no rodapé apenas quando informados.
    | Corretagem exige CRECI visível; contabilidade, CRC.
    */
    'creci' => env('SITE_CRECI'),
    'crc' => env('SITE_CRC'),

];
