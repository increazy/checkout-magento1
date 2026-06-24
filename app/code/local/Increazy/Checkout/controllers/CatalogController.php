<?php
class Increazy_Checkout_CatalogController extends Mage_Core_Controller_Front_Action
{
    public function productsAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $page      = isset($body['page'])      ? (int)$body['page']      : 1;
            $pageSize  = isset($body['page_size']) ? (int)$body['page_size'] : 100;
            $mediaBase = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product';

            $entityIds = $this->_parseIds($body['entity_ids'] ?? null);

            $collection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect('*')
                ->joinField('qty',         'cataloginventory/stock_item', 'qty',         'product_id=entity_id', null, 'left')
                ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', null, 'left');

            if ($entityIds) {
                $collection->addAttributeToFilter('entity_id', ['in' => $entityIds]);
            } else {
                $collection->setPageSize($pageSize)->setCurPage($page);
            }

            $total = $collection->getSize();
            $collection->load();

            $productIds = [];
            foreach ($collection as $p) {
                $productIds[] = (int)$p->getId();
            }

            // 5 batch queries — zero N+1
            $optionMap     = $this->_loadOptionMap();
            $galleryMap    = $this->_loadGalleryMap($productIds, $mediaBase);
            $productCatMap = $this->_loadProductCategoryMap($productIds);
            $relatedMap    = $this->_loadRelatedMap($productIds);
            $childrenMap   = $this->_loadChildrenMap($productIds);

            $allCatIds = [];
            foreach ($productCatMap as $catIds) {
                foreach ($catIds as $cid) { $allCatIds[$cid] = true; }
            }
            $categoryMap = $this->_loadCategoryMap(array_keys($allCatIds));

            $items = [];
            foreach ($collection as $product) {
                $data = $product->getData();
                $id   = (int)$product->getId();

                // URLs de imagem
                foreach (['image', 'small_image', 'thumbnail'] as $key) {
                    $path = $data[$key] ?? null;
                    $data[$key . '_url'] = ($path && $path !== 'no_selection') ? $mediaBase . $path : null;
                }

                // Labels resolvidos de atributos select/multiselect (ex: color 233 → "Preto")
                $labels = [];
                foreach ($optionMap as $code => $options) {
                    $val = $data[$code] ?? null;
                    if ($val !== null && $val !== '' && isset($options[$val])) {
                        $labels[$code] = $options[$val];
                    }
                }
                $data['attribute_labels'] = $labels;

                // Galeria completa
                $data['media'] = $galleryMap[$id] ?? [];

                // Categorias com dados (id, name, url_key, path, level)
                $catIds = $productCatMap[$id] ?? [];
                $data['category_ids'] = $catIds;
                $data['categories']   = array_values(array_filter(
                    array_map(function($cid) use ($categoryMap) {
                        return $categoryMap[$cid] ?? null;
                    }, $catIds)
                ));

                // Relacionados e filhos de configurável
                $data['related_ids']  = $relatedMap[$id]  ?? [];
                $data['children_ids'] = $childrenMap[$id] ?? [];

                $items[] = $data;
            }

            return [
                'page'      => $page,
                'page_size' => $pageSize,
                'total'     => $total,
                'last_page' => (int)ceil($total / $pageSize),
                'items'     => $items,
            ];
        }, function($body) { return true; });

        die(); // evita que o Magento appende HTML de erro no JSON
    }

    public function categoriesAction()
    {
        Mage::helper('increazy_checkout')->run($this, function($body) {
            $entityIds = $this->_parseIds($body['entity_ids'] ?? null);

            $collection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect(['name', 'is_active', 'parent_id', 'position', 'level', 'url_key', 'description', 'image', 'meta_title', 'meta_description']);

            if ($entityIds) {
                $collection->addAttributeToFilter('entity_id', ['in' => $entityIds]);
            } else {
                $collection->addIsActiveFilter();
            }

            $categories = [];
            foreach ($collection as $category) {
                $categories[] = $category->getData();
            }

            return $categories;
        }, function($body) { return true; });

        die();
    }

    private function _parseIds($raw)
    {
        if (!$raw) return null;
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return $ids ?: null;
    }

    // Todas as opções de atributos select/multiselect de produto — 1 query
    private function _loadOptionMap()
    {
        $resource = Mage::getSingleton('core/resource');
        $conn     = $resource->getConnection('core_read');

        $select = $conn->select()
            ->from(['o' => $resource->getTableName('eav_attribute_option')], [])
            ->join(
                ['v' => $resource->getTableName('eav_attribute_option_value')],
                'o.option_id = v.option_id AND v.store_id = 0',
                ['option_id' => 'o.option_id', 'label' => 'v.value']
            )
            ->join(
                ['a' => $resource->getTableName('eav_attribute')],
                'o.attribute_id = a.attribute_id',
                ['attribute_code']
            )
            ->where('a.entity_type_id = ?', 4); // catalog_product

        $map = [];
        foreach ($conn->fetchAll($select) as $row) {
            $map[$row['attribute_code']][$row['option_id']] = $row['label'];
        }
        return $map;
    }

    // Galeria de imagens de todos os produtos — 1 query
    private function _loadGalleryMap(array $ids, $mediaBase)
    {
        if (!$ids) return [];

        $resource = Mage::getSingleton('core/resource');
        $conn     = $resource->getConnection('core_read');

        $select = $conn->select()
            ->from(['g' => $resource->getTableName('catalog_product_entity_media_gallery')], ['entity_id', 'value'])
            ->joinLeft(
                ['v' => $resource->getTableName('catalog_product_entity_media_gallery_value')],
                'g.value_id = v.value_id AND v.store_id = 0',
                ['label', 'position', 'disabled']
            )
            ->where('g.entity_id IN (?)', $ids)
            ->where('v.disabled = 0 OR v.disabled IS NULL')
            ->order('g.entity_id')
            ->order('v.position ASC');

        $map = [];
        foreach ($conn->fetchAll($select) as $row) {
            $map[(int)$row['entity_id']][] = [
                'file'     => $row['value'],
                'url'      => $mediaBase . $row['value'],
                'label'    => $row['label'],
                'position' => (int)$row['position'],
            ];
        }
        return $map;
    }

    // IDs de categorias por produto — 1 query
    private function _loadProductCategoryMap(array $ids)
    {
        if (!$ids) return [];

        $resource = Mage::getSingleton('core/resource');
        $conn     = $resource->getConnection('core_read');

        $select = $conn->select()
            ->from($resource->getTableName('catalog_category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $ids);

        $map = [];
        foreach ($conn->fetchAll($select) as $row) {
            $map[(int)$row['product_id']][] = (int)$row['category_id'];
        }
        return $map;
    }

    // Dados de categorias por IDs — 1 query
    private function _loadCategoryMap(array $ids)
    {
        if (!$ids) return [];

        $collection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['name', 'url_key', 'level', 'path', 'parent_id', 'is_active', 'position'])
            ->addAttributeToFilter('entity_id', ['in' => $ids]);

        $map = [];
        foreach ($collection as $cat) {
            $map[(int)$cat->getId()] = $cat->getData();
        }
        return $map;
    }

    // IDs de produtos relacionados — 1 query
    private function _loadRelatedMap(array $ids)
    {
        if (!$ids) return [];

        $resource = Mage::getSingleton('core/resource');
        $conn     = $resource->getConnection('core_read');

        $select = $conn->select()
            ->from($resource->getTableName('catalog_product_link'), ['product_id', 'linked_product_id'])
            ->where('link_type_id = 1') // 1 = related
            ->where('product_id IN (?)', $ids);

        $map = [];
        foreach ($conn->fetchAll($select) as $row) {
            $map[(int)$row['product_id']][] = (int)$row['linked_product_id'];
        }
        return $map;
    }

    // IDs de filhos de produtos configuráveis — 1 query
    private function _loadChildrenMap(array $ids)
    {
        if (!$ids) return [];

        $resource = Mage::getSingleton('core/resource');
        $conn     = $resource->getConnection('core_read');

        $select = $conn->select()
            ->from($resource->getTableName('catalog_product_super_link'), ['parent_id', 'product_id'])
            ->where('parent_id IN (?)', $ids);

        $map = [];
        foreach ($conn->fetchAll($select) as $row) {
            $map[(int)$row['parent_id']][] = (int)$row['product_id'];
        }
        return $map;
    }
}
