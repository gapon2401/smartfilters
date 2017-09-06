<?php

return array(
    'name' => 'Smart Filters',
    'description' => 'Отличное дополнение фильтров в категории',
    'vendor'=>'972539',
    'version'=>'2.0.0',
    'img'=>'img/smartfilters.gif',
	'handlers'=> array(
        'frontend_category' => 'frontendCategory',
        'frontend_head' => 'frontendHead',
        'frontend_products' => 'frontendProducts',
        'backend_category_dialog' => 'backendCategoryDialog',
        'category_save' => 'categorySave',
        'products_collection.filter' => 'productsCollectionFilter',
    ),
    'shop_settings' => true,
);
//EOF
