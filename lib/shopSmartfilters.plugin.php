<?php

class shopSmartfiltersPlugin extends shopPlugin {

    const THEME_FILE = 'plugin.smartfilters.html';

    const DISPLAY_TEMPLATE = '1';
    const DISPLAY_HELPER = '2';
    const DISPLAY_THEME = '3';

    /************
     * Хелперы
     ************/

    /**
     * Возвращает HTML с фильтром для категории.
     *
     * @param $category_id
     * @return string
     */
    public static function get($category_id)
    {
        try {
            if(wa('shop')->getPlugin('smartfilters')->getSettings('enabled') === self::DISPLAY_HELPER) {
                return self::display($category_id);
            }
        } catch (waException $e) {
        }
        return '';
    }

    /**
     * Возвращает массив фильтров для категории.
     *
     * @param $category_id
     * @return array
     */
    public static function getFiltersForCategory($category_id) {
        static $filters;
        if($filters === null) {
            $filters = array();
        }
        if(!isset($filters[$category_id])) {
            $feature_model = new shopSmartfiltersPluginFeatureModel();
            $filters[$category_id] = $feature_model->getByCategoryId($category_id);
            shopSmartfiltersPluginMylangFilters::prepare($filters[$category_id]);
        }

        return ifempty($filters[$category_id], array());
    }


    /**
     * Хелпер для вывода JS в category.html
     * Нужен, если хук frontend_category не подгружается аяксом.
     *
     * @param $category_id
     * @return string
     */
    public static function categoryTheme($category_id)
    {
        if(!$filters = self::getFiltersForCategory($category_id)) {
            return '';
        }
        try {

            $view = wa()->getView();
            $view->assign('filters', $filters); // rewrite default var
            $plugin = wa('shop')->getPlugin('smartfilters');
            $view->assign('smartfilters', $plugin->getSettings());
            return $view->fetch($plugin->path.'/templates/hooks/frontendCategoryTheme.html');
        } catch (Exception $e) {
        }

        return '';
    }


    /**********************
     * Обработчики хуков
     **********************/


    /**
     * @return string
     */
    public function frontendHead()
    {
        if(waRequest::param('action') == 'category') {
            $e = $this->getSettings('enabled');

            if ($e && ($e !== self::DISPLAY_THEME) && !$this->getSettings('ui_slider')) {
                $view = wa()->getView();
                return $view->fetch($this->path . '/templates/hooks/frontendHead.html');
            } elseif ($e === self::DISPLAY_THEME) {
                $view = wa()->getView();
                return $view->fetch($this->path . '/templates/hooks/frontendHeadTheme.html');
            }
        }

        return '';
    }

    /**
     * @param $category
     * @return string
     */
    public function frontendCategory($category)
    {
        if(!$enabled = $this->getSettings('enabled')) {
            return '';
        }

        $result = '';

        if ($enabled === self::DISPLAY_TEMPLATE) {
            $result = self::display($category['id']);
        } elseif ($enabled === self::DISPLAY_THEME) {
            $result = self::categoryTheme($category['id']);
        }

        $filters = self::getFiltersForCategory($category['id']);
        if ($filters && $this->getSettings('color_change')) {

            $view = wa('shop')->getView();
            $products = $view->getVars('products');

            $p = new shopSmartfiltersPluginPrepareProducts();
            $products = $p->prepare($products, $filters);
            $view->assign('products', $products);
        }

        return $result;
    }

    /**
     * @param $settings
     * @return string
     */
    public function backendCategoryDialog($settings)
    {

        $feature_model = new shopFeatureModel();
        $selectable_and_boolean_features = $feature_model
            ->select('*')
            ->where("(selectable=1 OR type='boolean' OR type='double' OR type LIKE 'dimension\.%' OR ".
                "type LIKE 'range\.%') AND parent_id IS NULL")
            ->fetchAll('id');
        $filter = $settings['smartfilters'] !== null ? explode(',', $settings['smartfilters']) : null;
        $feature_filter = array();
        $features['price'] = array(
            'id' => 'price',
            'name' => _wp('Price')
        );
        $features['sf_available'] = array(
            'id' => 'sf_available',
            'name' => _wp('In stock')
        );
        $features += $selectable_and_boolean_features;

        $_smartfilters_name = $settings['smartfilters_name'] !== null ?
            explode(',', $settings['smartfilters_name']) : array();

        $smartfilters_name = array();
        if (!empty($filter)) {
            foreach ($filter as $k => $feature_id) {
                $smartfilters_name[$feature_id] = ifempty($_smartfilters_name[$k]);
                $feature_id = trim($feature_id);
                if (isset($features[$feature_id])) {
                    $feature_filter[$feature_id] = $features[$feature_id];
                    $feature_filter[$feature_id]['checked'] = true;
                    unset($features[$feature_id]);
                }
            }
        }
        $data = array(
            'allow_smartfilters' => (bool)$filter,
            'smartfilters' => $feature_filter + $features,
            'smartfilters_name' => $smartfilters_name
        );


        $view = wa()->getView();
        $view->assign($data);

        return $view->fetch($this->path.'/templates/hooks/backendCategoryDialog.html');
    }

    /**
     * @param $data
     */
    public function categorySave(&$data)
    {
        if(!empty($data['id'])) {
            if(!waRequest::post('smartfilters')) {
                return;
            }
            if (waRequest::post('allow_smartfilters')) {
                $smartfilters = implode(',', waRequest::post('smartfilters'));
                $smartfilters_name = implode(',', waRequest::post('smartfilters_name'));
            } else {
                $smartfilters = null;
                $smartfilters_name = null;
            }
            $model = new shopCategoryModel();

            if(waRequest::post('smartfilters_descendants')) {
                $parent = $model->getById($data['id']);

                $model->query('UPDATE '.$model->getTableName().' '
                    .'SET smartfilters = s:smartfilters, smartfilters_name = s:smartfilters_name '.
                    'WHERE left_key >= i:left_key AND right_key <= i:right_key',
                    array(
                        'smartfilters' => $smartfilters,
                        'smartfilters_name' => $smartfilters_name,
                        'left_key'  => $parent['left_key'],
                        'right_key' => $parent['right_key'],
                    )
                );
            } else {
                $model->updateById($data['id'], array(
                    'smartfilters' => $smartfilters,
                    'smartfilters_name' => $smartfilters_name,
                ));
            }
        }
    }

    public function frontendProducts($params)
    {
        return;
        $feature_code = $this->getSettings('color_feature');
        $color = waRequest::get($feature_code, array(), waRequest::TYPE_ARRAY_INT);
        if($color && !empty($params['products'])) {
            $this->prepareProductPhotos($color, $params['products']);
        }
    }

    /**
     * @param shopProductsCollection $collection
     */
    public function productsCollectionFilter($collection)
    {
        $hash = $collection->getHash();

        if(is_array($hash) && !empty($hash[0]) && ($hash[0] == 'category')) {

            if($this->getSfAvailable()) {
                shopSmartfiltersPluginProductsCollection::prepareCollection($collection);
            }
        }
    }


    public function getSfAvailable()
    {
        if(wa()->getEnv() != 'frontend') {
            return false;
        }
        if(waRequest::get('sf_available')) {
            return true;
        }
        if($this->getSettings('sf_available')) {
            return true;
        }
        return false;
    }

    /************
     * Всякое
     ************/

    /**
     * @param $category_id
     * @return string
     */
    private static function display($category_id)
    {
        if ($filters = self::getFiltersForCategory($category_id)) {
            $list = new shopSmartfiltersPluginShowAction();
            $list->setFilters($filters);
            return $list->display(false);
        }
        return '';
    }

    /**
     * @deprecated use shopSmartfiltersPluginPrepareProducts instead
     * @param $color
     * @param $products
     */
    private function prepareProductPhotos($color, &$products)
    {
        if(empty($products)) {
            return;
        }

        $feature_code = $this->getSettings('color_feature');

        if(empty($feature_code)) {
            return;
        }

        $product_ids = array_keys($products);
        $fm = new shopFeatureModel();
        $feature = $fm->getByCode($feature_code);

        if(empty($feature)) {
            return;
        }

        $pfm = new shopProductFeaturesModel();
        $product_skus = $pfm->query('SELECT product_id, sku_id FROM '.$pfm->getTableName().' '.
            'WHERE product_id IN (:product_ids) AND feature_id IN(:feature_ids) '.
            'AND feature_value_id IN(:value_ids) AND sku_id IS NOT NULL '.
            'GROUP BY product_id',
            array(
                'product_ids' => $product_ids,
                'feature_ids' => array($feature['id']),
                'value_ids' => $color
            )
        )->fetchAll('product_id', true);

        if($product_skus) {
            $images = $pfm->query(
                'SELECT i.* FROM shop_product_images i '.
                'JOIN shop_product_skus s ON s.image_id = i.id '.
                'WHERE s.id IN (:sku_ids)',
                array('sku_ids' => array_values($product_skus))
            )->fetchAll('product_id');

            foreach ($images as $product_id => $image) {
                if(isset($products[$product_id])) {
                    $products[$product_id]['image_id'] = $image['id'];
                    $products[$product_id]['image_filename'] = $image['filename'];
                    $products[$product_id]['ext'] = $image['ext'];
                }
            }
        }

    }
}