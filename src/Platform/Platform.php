<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/30 0030
 * Time: 14:28
 */

namespace MorePlatform\Platform;

class Platform
{


    public function buildData($goods)
    {

        //获取商品所有数据
        $goodsInfo=$goods->toArray();
        //扩展属性
        $extendGoodsAttribute=$goods->goodsAttribute()->get();
        foreach($extendGoodsAttribute as $attributeValue)
        {
            //dd($attributeValue);
            $attributeMeta=$attributeValue->attributes()->first();
            if(in_array($attributeValue->attribute_type,['radio','checkbox']))
            {
                $attribute=$attributeMeta->childrenAttributes()->where(function($query)use($attributeValue){
                    $query->where('id',$attributeValue->attribute_value);
                })->first();
                $attributeMeta=$attributeMeta->toArray();
                $attribute=$attribute->toArray();
                $goodsInfo['extend_attribute'][$attributeMeta['id']]['attribute_meta']=$attributeMeta;
                $goodsInfo['extend_attribute'][$attributeMeta['id']]['attribute_value'][]=$attribute;
            }
            else
            {
                $attributeMeta=$attributeMeta->toArray();
                $attribute=$attributeValue->toArray();
                $attributeMeta['value']=$attribute;
                $goodsInfo['extend_attribute'][$attributeMeta['id']]['attribute_meta']=$attributeMeta;
                $goodsInfo['extend_attribute'][$attributeMeta['id']]['attribute_value'][]=$attribute;
            }

        }
        $goodsInfo['extend_attribute']=array_values($goodsInfo['extend_attribute']);
        //获取图片
        $photos=$goods->photo()->get();
        $photost=[];
        foreach($photos as  &$photo)
        {
            $photo=$photo->toArray();
            $photo['path_url']=env('QINIU_DOMAINS_HTTPS').$photo['path'];
            $photost[]=$photo;
        }
        $goodsInfo['photo_list']=$photost;



        //分类和品牌
        $brand= $goods->categoryBrandRelation()->first();

        $goodsInfo['brand']=$brand->toArray();
        $childCategory=$goods->category()->first();
        $parentCateory=$childCategory->childCategory()->first();
        $goodsInfo['child_category']=$childCategory->toArray();
        $goodsInfo['parent_category']=$parentCateory->toArray();
        return $goodsInfo;
    }


}