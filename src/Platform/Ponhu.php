<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19 0019
 * Time: 14:26
 */

namespace MorePlatform\Platform;
use  GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
class Ponhu extends Platform
{
    private $httpClient;
    private $loginInfo;
    private $platformAccount;
    private $goods;
    private $goodsInfo;
    private $shareHistory;
    private $isEdit;
    private $parseGoodsInfo;
    private $error = '';
    private $errorMessage = '';
    public function __construct()
    {
        $this->httpClient = new Client(
            [
                'base_uri' => 'http://www.ponhu.cn/',
                'headers' =>
                    [
                        'Accept'=>'*/*',
                        'User-Agent'=>'FitTiger/3.6.5 (iPhone; iOS 11.4.1; Scale/2.00)',
                        'Content-Type' => "application/x-www-form-urlencoded"
                    ]
            ]);
    }

    /**
     * @param $account 登录
     */
    public function login($accountInfo,$force=false)
    {
        $this->platformAccount=$accountInfo;
        if(!$force)
        {
            if(!empty($accountInfo->login_info))
            {
                $result=$this->checkLogin($accountInfo->login_info);
                if($result)
                {
                    return true;
                }
            }
        }
        //登录相信
        $data['device_number']='101d85590911a8247ad';
        $data['location']='';
        $data['mobile']=$accountInfo->account;
        $data['os']='1';
        $data['password']=$accountInfo->password;
        $data['sub']='1';
        $data['v']='3.6.5';
        try
        {
            $response=$this->httpClient->post('/index.php/Iosapi/User/login',['form_params'=>$data]);
            $body = @iconv("UTF-8", "GBK//IGNORE", $response->getBody()->getContents());
            $result = @iconv("GBK", "UTF-8//IGNORE", $body);
            $result=json_decode($result,true);
            if(isset($result['message']['user']['token']))
            {
                $this->loginInfo['token']=$result['message']['user']['token'];
                $accountInfo->login_info=json_encode($this->loginInfo);
                $accountInfo->save();
                return true;
            }
            else{
                $this->error=422;
                $this->errorMessage=$result['message'];
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }

    /**
     * @param $plaform
     * @param $goods
     */
    public function buildData($goods)
    {
        //获取历史数据
        $this->goods=$goods;
        $this->shareHistory=$goods->share_history()->where(function($query){
            $query= $query->where('status',1);
            $query->where('platform','ponhu');
        })->first();

        if(isset($this->shareHistory)&&!empty($this->shareHistory->platform_goods_id)&&$this->shareHistory->platform_account_id==$this->platformAccount->id)
        {
            $this->isEdit=true;
        }
        else{
            $this->isEdit=false;
        }
        try{
            $goodsInfo=parent::buildData($goods);
            $this->parseGoodsInfo=$goodsInfo;
            $ponhuData['os']="1";
            $ponhuData['trans_type']="3";
            $ponhuData['v']='3.6.5';
            $ponhuData['device_number']='101d85590911a8247ad';
            $ponhuData['token']=$this->loginInfo['token'];
            //描述
            if(strlen($goodsInfo['description'])<=75)
            {
                $this->error=422;
                $this->errorMessage='描述不能低于25个字符';
                return false;
            }
            $ponhuData['desc']=$goodsInfo['description'];

            //宝贝定价
            if(empty($goodsInfo['price']))
            {
                $this->error=422;
                $this->errorMessage='卖价为空';
                return false;
            }
            $ponhuData['price']=$goodsInfo['price'];


            //分类id
            if(empty($goodsInfo['parent_category']['ponhu_category_id'])){
                $this->error=422;
                $this->errorMessage='父级分类为空';
                return false;
            }
            $ponhuData['fcateid']=$goodsInfo['parent_category']['ponhu_category_id'];
            //分类id
            if(empty($goodsInfo['child_category']['ponhu_category_id'])){
                $this->error=422;
                $this->errorMessage='子分类为空';
                return false;
            }
            $ponhuData['scateid']=$goodsInfo['child_category']['ponhu_category_id'];

            //品牌
            if(empty($goodsInfo['brand']['ponhu_brand_id'])){
                $this->error=422;
                $this->errorMessage='品牌为空';
                return false;
            }
            //新旧
            switch ($goodsInfo['fineness'])
            {
                case 100:
                    $ponhuData['colourid']="1";
                    break;
                case 99:
                    $ponhuData['colourid']="1";
                    break;
                case 98:
                    $ponhuData['colourid']="2";
                    break;
                case 95:
                    $ponhuData['colourid']="3";
                    break;
                case 90:
                    $ponhuData['colourid']="4";
                    break;
                case 85:
                    $ponhuData['colourid']="5";
                    break;
                case 80:
                    $ponhuData['colourid']="6";
                    break;
            }
            if(empty($ponhuData['colourid']))
            {
                $this->error=422;
                $this->errorMessage='成色为空';
                return false;
            }
            //扩展属性
            $ponhuData['brandid']=$goodsInfo['brand']['ponhu_brand_id'];
            foreach($goodsInfo['extend_attribute'] as $attribute)
            {
                if($attribute['attribute_meta']['ponhu_attribute_id'])
                {
                    if(in_array($attribute['attribute_meta']['attribute_type'],['radio','checkbox']))
                    {
                        $value=array_map(function($v){
                            return $v['ponhu_attribute_id'];
                        },$attribute['attribute_value']);
                        $ponhuData[$attribute['attribute_meta']['ponhu_attribute_id']]=implode(",",$value);
                    }
                    else{
                        $attributes=end($attribute['attribute_value']);
                        $ponhuData[$attribute['attribute_meta']['ponhu_attribute_id']]=$attributes['attribute_value'];
                    }
                }
            }

            //是否复古
            if(empty($ponhuData['isfugu']))
            {
                $this->error=422;
                $this->errorMessage='是否复古为空';
                return false;
            }
            $ponhuData['sendtime']=empty($ponhuData['sendtime'])?"1":$ponhuData['sendtime'];
            //是否包邮
            if(!isset($ponhuData['postage']))
            {
                $this->error=422;
                $this->errorMessage='是否包邮为空';
                return false;
            }

            //适用人群
            if(!isset($ponhuData['fitgroup']))
            {
                $this->error=422;
                $this->errorMessage='适用人群为空';
                return false;
            }

            if($this->isEdit)
            {
                //编辑该平台的数据
                $ponhuData['goodsid']=$this->shareHistory->platform_goods_id;
                $shareHistoryToData=json_decode($this->shareHistory->to_data,true);
                $ponhuData['delimg_str']=$shareHistoryToData['goods_images'];

            }
            //处理图片
            if(count($goodsInfo['photo_list'])<4)
            {
                $this->error=422;
                $this->errorMessage='图片不能少于4张';
                return false;
            }
            $disk = Storage::disk('qiniu');
            foreach($goodsInfo['photo_list'] as $photo)
            {
                $token=$this->getToken();
                if(!$token)
                {
                    return false;
                }
                $disk->getDriver()->withUploadToken($token);
                $filename=$this->getMicrotime()."_".$goods->id;
                $result=$disk->put($filename,file_get_contents($photo['path_url']));
                if($result)
                {
                    $ponhuData['goods_images'][]="http://7xl0wg.com2.z0.glb.qiniucdn.com/".$filename;
                }
                else{
                    $this->error=422;
                    $this->errorMessage='上传图片失败,请重新上传';
                    return false;
                }

            }
            $ponhuData['goods_images']=implode(",",$ponhuData['goods_images']);
            $this->goodsInfo=$ponhuData;
            return true;

        }
        catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }


    /**
     * @return bool 提交数据
     */
    public function send()
    {
        try
        {
            if($this->isEdit)
            {
                $url="/index.php/Iosapi/Releasegoods/editGoods";
            }
            else {
                $url="/index.php/Iosapi/Releasegoods/postGoods";
            }
            $response=$this->httpClient->post($url,['form_params'=>$this->goodsInfo]);
            $body = @iconv("UTF-8", "GBK//IGNORE", $response->getBody()->getContents());
            $result = @iconv("GBK", "UTF-8//IGNORE", $body);
            $result=json_decode($result,true);
            if($response->getStatusCode()==200&&$result['stat']==200)
            {
                if(!empty($this->shareHistory))
                {
                    $this->shareHistory->status=2;
                    $this->shareHistory->save();
                }
                if($this->isEdit)
                {
                    $goodsId=$this->shareHistory->platform_goods_id;
                }
                else{
                    $goodsId=$this->getGoodsId($this->goods->id);

                }
                $data['platform_id']=$this->platformAccount->platform_id;
                $data['platform_account_id']=$this->platformAccount->id;
                $data['platform_price']=$this->goodsInfo['price'];
                $data['platform_goods_id']=$goodsId;
                $data['user_id']=$this->goods->user_id;
                $data['status']=1;
                $data['from_data']=json_encode($this->parseGoodsInfo);
                $data['to_data']=json_encode($this->goodsInfo);
                $data['platform']=$this->platformAccount->platform;
                $this->goods->share_history()->create($data);
                return true;
            }
            else{
                $this->error=422;
                $this->errorMessage=$result['message'];
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }

    }

    /**
     * @param $goodsId 获取货物id
     * @return bool
     */
    private function getGoodsId($goodsId)
    {
        try
        {
            //var_dump($this->platformAccount);
            $url='/index.php/Iosapi/Mypublish/myPostList';
            $data['device_number']='101d85590911a8247ad';
            $data['os']=1;
            $data['p']=1;
            $data['rows']=10;
            $data['token']=$this->loginInfo['token'];
            $data['type']=1;
            $data['v']='3.6.5';
            $response=$this->httpClient->post($url,['form_params'=>$data]);
            $body = @iconv("UTF-8", "GBK//IGNORE", $response->getBody()->getContents());
            $result = @iconv("GBK", "UTF-8//IGNORE", $body);
            $result=json_decode($result,true);
            //var_dump($result);
            if($response->getStatusCode()==200&&$result['stat']==200)
            {
                foreach($result['message'] as $item)
                {
                    $goodsImages=explode("_",$item['goods_images']);
                    //var_dump($goodsImages);
                    if($goodsId==$goodsImages[1])
                    {
                        return $item['id'];
                    }
                }
                $this->error=422;
                $this->errorMessage="未找到对应的商品";
                return false;
            }
            else{
                $this->error=422;
                $this->errorMessage=$result['message'];
                return false;
            }
        }catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }

    /**
     * @param $goods 下架
     */
    public function downGoods($goods)
    {
        try{
            $this->shareHistory=$goods->share_history()->where(function($query){
                $query= $query->where('status',1);
                $query->where('platform','ponhu');
            })->first();
            if($this->shareHistory->platform_account_id==$this->platformAccount->id)
            {
                $this->error=422;
                $this->errorMessage='该货物不在此账户下，无法下架';
                return false;
            }
            if(!empty($this->shareHistory)&&!empty($this->shareHistory->platform_goods_id))
            {
                $url='/index.php/Iosapi/Mypublish/underGoods';
                $data['device_number']='101d85590911a8247ad';
                $data['os']=1;
                $data['goodsid']=10;
                $data['token']=$this->loginInfo['token'];
                $data['v']='3.6.5';
                $response=$this->httpClient->post($url,['form_params'=>$data]);
                if($response->getStatusCode()==200)
                {
                    $this->shareHistory->delete();
                    return true;
                }
                else{
                    $this->error=422;
                    $this->errorMessage='下架失败，请联系管理员';
                    return false;
                }
            }
            else
            {
                $this->error=422;
                $this->errorMessage='未找到记录，无法下架';
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }



    /**
     * @return string 获取毫秒时间戳
     */
    private function getMicrotime()
    {
        $mtime = explode(' ', microtime());
        $mtime["0"] = (int)($mtime["0"] * 1000);
        return $mtime["1"] . $mtime["0"];
    }

    /**
     * @param $loginInfo 检查是否登录信息有效
     * @return bool
     */
    private function checkLogin($loginInfo)
    {
        try{
            $loginInfo=json_decode($loginInfo,true);
            $data['device_number']='101d85590911a8247ad';
            $data['os']='1';
            $data['token']=$loginInfo['token'];
            $data['v']='3.6.5';
            $result=$this->httpClient->post('/index.php/Iosapi/Profile/setUserInfo',['form_params'=>$data ]);
            $body= json_decode($result->getBody()->getContents(),true);
            if($result->getStatusCode()==200&&$body['stat']==200)
            {
                $this->loginInfo=$loginInfo;
                return true;
            }
            else{
                return false;

            }
        }
        catch (\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }

    /**
     * @return bool 获取七牛token
     */
    private function  getToken()
    {
        try{
            $url="/index.php/Iosapi/Qiniu/getQiniuToken";
            $data['device_number']='101d85590911a8247ad';
            $data['os']='1';
            $data['token']=$this->loginInfo['token'];
            $data['v']='3.6.5';
            $response=$this->httpClient->post($url,['form_params'=>$data ]);
            $body = @iconv("UTF-8", "GBK//IGNORE", $response->getBody()->getContents());
            $result = @iconv("GBK", "UTF-8//IGNORE", $body);
            $result=json_decode($result,true);
            if($response->getStatusCode()==200)
            {
                return $result['message']['token'];
            }
            else{
                return false;

            }
        }
        catch(\Exception $e)
        {
            $this->error=$e->getCode();
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }


    public function getError()
    {
        // dd(21312);
        return $this->error;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }



}