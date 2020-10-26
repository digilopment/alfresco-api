<?php

namespace BikeModel;

class DataModel
{

    const IMG_SERVICE = 'http://media.storage.digilopment.com/dnt-view/data/uploads/formats/950/';
    const ALFRESCO_SRV = 'http://192.168.84.143:8080/alfresco/api/-default-/public/alfresco/versions/1/nodes/-root-/children';
    const ALFRESCO_LOGIN = 'admin';
    const ALFRESCO_PSSWD = 'admin';
    const NUMBER_OF_PRODUCTS_TO_IMPORT = 2;

    protected $jsonProducts;
    protected $jsonMeta;
    protected $jsonImages;
    protected $jsonCategories;

    public function loadData()
    {
        $this->jsonProducts = json_decode(file_get_contents('https://www.bicyklehlohovec.sk/dnt-api/multi/json/products/3'));
        $this->jsonMeta = json_decode(file_get_contents('https://www.bicyklehlohovec.sk/dnt-api/multi/json/products-meta/4'));
        $this->jsonImages = json_decode(file_get_contents('https://www.bicyklehlohovec.sk/dnt-api/multi/json/products-images/5'));
        $this->jsonCategories = json_decode(file_get_contents('https://www.bicyklehlohovec.sk/dnt-api/multi/json/products-categories/6'));
    }

    protected function getMeta($posId)
    {
        $final = [];
        foreach ($this->jsonMeta->items as $item) {
            if ($item->post_id == $posId) {
                $final[$item->key] = $item->value;
            }
        }
        return $final;
    }

    protected function setCategoryTree($post_category_id)
    {
        $charIndex = '';
        foreach ($this->jsonCategories->items as $item) {
            if ($item->id_entity == $post_category_id) {
                $charIndex = $item->char_index;
            }
        }
        $charIndex = str_replace('B-', '', $charIndex);
        $charIndexIds = str_replace('-E', '', $charIndex);
        $idsArr = explode('-', $charIndexIds);
        $catNames = [];
        foreach ($idsArr as $id) {
            $catNames[] = $this->getCategory($id);
        }
        $final['cat_tree'] = implode('/', $catNames);
        return $final;
    }

    protected function getCategory($post_category_id)
    {

        foreach ($this->jsonCategories->items as $item) {
            if ($item->id_entity == $post_category_id) {
                return $item->name;
            }
        }
        return false;
    }

    protected function getImage($img)
    {
        $final = [];
        foreach ($this->jsonImages->items as $item) {
            if ($item->id_entity == $img) {
                $final['img_url'] = self::IMG_SERVICE . $item->name;
            }
        }
        return $final;
    }

    public function getProduct()
    {
        $jsonProducts = $this->jsonProducts;
        $final = [];

        $i = 1;
        foreach ($jsonProducts->items as $key => $item) {

            if ($i <= self::NUMBER_OF_PRODUCTS_TO_IMPORT) {
                if ($item->type == 'product') {
                    $final[$key] = array_merge(
                            $this->getMeta($item->id_entity),
                            $this->setCategoryTree($item->post_category_id),
                            $this->getImage($item->img)
                    );
                    $final[$key]['id'] = $item->id;
                    $final[$key]['id_entity'] = $item->id_entity;
                    $final[$key]['group_id'] = $item->group_id;
                    $final[$key]['vendor_id'] = $item->vendor_id;
                    $final[$key]['type'] = $item->type;
                    $final[$key]['name'] = $item->name;
                    $final[$key]['name_url'] = $item->name_url;
                    $final[$key]['content'] = $item->content;
                    $final[$key]['service'] = $item->service;
                    $final[$key]['img'] = $item->img;
                    $final[$key]['datetime_creat'] = $item->datetime_creat;
                }
            }
            $i++;
        }

        $this->finalData = $final;
    }

    public function notHtml($str)
    {
        //return htmlspecialchars($str); 
        $str = strip_tags($str);
        $str = trim($str);
        return $str;
    }

    public function downloadFile($url, $cesta)
    {
        $img = explode('/', $url);
        $array = $img;
        if (!is_array($array)) {
            return $array;
        }
        if (!count($array)) {
            return null;
        }
        end($array);
        $fotka = $array[key($array)];

        $img = $cesta . $fotka;
        $pripona = explode('.', $fotka);
        if (!isset($pripona[1])) {
            //fotka nema v url adrese priponu
            $fotka = self::name_url($fotka) . '.jpg';
            $img = $cesta . $fotka;
        }

        $fileName = explode('.', $fotka);
        if (file_get_contents($url)) {
            file_put_contents('file.' . $fileName[1], file_get_contents($url));
            return array('file' => 'file.' . $fileName[1], 'path' => $cesta);
        }
        return false;
    }

    protected function curlCustomPostfields($ch, array $assoc = array(), array $files = array())
    {
        // invalid characters for "name" and "filename"
        $disallow = array('\0', '\"', '\r', '\n');

        // build normal parameters
        foreach ($assoc as $k => $v) {
            $k = str_replace($disallow, '_', $k);
            $body[] = implode("\r\n", array(
                'Content-Disposition: form-data; name="' . $k . '"',
                '',
                filter_var($v),
            ));
        }

        // build file parameters
        foreach ($files as $k => $v) {
            $data = file_get_contents($v);
            $k = str_replace($disallow, '_', $k);
            $v = str_replace($disallow, '_', $v);
            $body[] = implode("\r\n", array(
                'Content-Disposition: form-data; name="' . $k . '"; filename="' . $v . '"',
                '',
                $data,
            ));
        }

        // generate safe boundary
        do {
            $boundary = '---------------------' . md5(mt_rand() . microtime());
        } while (preg_grep('/' . $boundary . '/', $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        // set options
        return curl_setopt_array($ch, array(
            CURLOPT_URL => self::ALFRESCO_SRV,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => self::ALFRESCO_LOGIN . ':' . self::ALFRESCO_PSSWD,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => implode("\r\n", $body),
            CURLOPT_HTTPHEADER => array(
                'Content-length: ' . strlen(implode("\r\n", $body)),
                "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
                'Accept: application/json',
            ),
        ));
    }

    public function import()
    {
        $i = 1;
        foreach ($this->finalData as $item) {
            if ($item['img'] && $item['img_url']) {
                $file = $this->downloadFile($item['img_url'], '/');
            } else {
                $file['file'] = 'file.jpg';
            }

            $curlHandler = curl_init();
            $files = [
                'filedata' => $file['file']
            ];
            $assoc = [
                'name' => $item['name_url'] . '',
                'nodeType' => 'bm:produkt',
                'cm:title' => $item['name'],
                'cm:description' => $this->notHtml($item['content']),
                'relativePath' => $item['cat_tree'],
                'autoRename' => 'true',
                'renditions' => 'doclib',
                'mimeType' => 'application/json',
                'cm:author' => 'tomas',
                'assocType' => 'bm:Produkt',
                'bm:price' => $item['price'],
                'stackTrace' => 1
            ];
            $this->curlCustomPostfields($curlHandler, $assoc, $files);
            $response = curl_exec($curlHandler);
            print($i . ' => ' . json_decode($response)->entry->name) . " => " . $item['name'] . " | " . $item['type'] . "<br/>";
            $i++;
        }
    }

}

$model = new DataModel();
$model->loadData();
$model->getProduct();
$model->import();
