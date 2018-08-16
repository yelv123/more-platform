<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/20 0020
 * Time: 11:54
 */

namespace MorePlatform\Platform;

use  GuzzleHttp\Client;

class Xinshang extends Platform
{
    private $httpClient;
    private $loginInfo;
    private $headers = [];
    private $platformAccount;
    private $error = '';
    private $errorMessage = '';
    private $parseGoodsInfo;
    public function __construct()
    {
        $this->headers = [
            'Accept' => '*/*',
            'User-Agent' => '心上 3.3.1 rv:3 (iPhone; iOS 11.4.1; zh_CN)',
            'appId' => "com.91sph.SPH0",
            'market' => "appStore",
            'channel' => "ios",
            'appVersionCode' => "20",
            'appVersion' => "3.3.1",
            'deviceType' => "phone",
            'deviceToken' => "8AC1C0D8-B04D-4BE1-8C95-808E0431591D"
        ];
        $this->httpClient = new Client(['base_uri' => 'https://api.91xinshang.com/', 'headers' => $this->headers]);
    }


    public function login($accountInfo, $force = false)
    {

        $this->platformAccount=$accountInfo;
        if (!$force) {

            if (!empty($accountInfo->login_info)) {

                $result = $this->checkLogin($accountInfo->login_info);

                if ($result) {
                    return true;
                }
            }
        }
        try {
            $url = "/user/login";
            $data['account'] = $accountInfo->account;
            $data['password'] = $accountInfo->password;
            $data['longitude'] = "";
            $data['latitude'] = "";
            $data['cityName'] = "";
            $data['loginType'] = 1;
            $data['timestamp'] = $this->getMicrotime();
            $data['sign'] = $this->makeSign($data);

            $response = $this->httpClient->post($url, ['form_params' => $data]);
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['code'] == 0) {
                $this->loginInfo = $result['data'];
                $this->headers['sessionId'] = $result['data']['sessionId'];
                $this->httpClient = new Client(['base_uri' => 'https://api.91xinshang.com/', 'headers' => $this->headers]);
                $accountInfo->login_info = json_encode($result['data']);
                $accountInfo->save();
                return true;
            } else {
                $this->error = 422;
                $this->errorMessage = $result['msg'];
                return false;
            }
        } catch (\Exception $e) {

            $this->error = 422;
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }

    public function buildData($goods)
    {
        //获取历史数据
        $this->goods=$goods;
        $this->shareHistory=$goods->share_history()->where(function($query){
            $query= $query->where('status',1);
            $query->where('platform','xinshang');
        })->first();
        if(isset($this->shareHistory)&&!empty($this->shareHistory->platform_goods_id)&&$this->shareHistory->platform_account_id==$this->platformAccount->id)
        {
            $this->isEdit=true;
        }
        else{
            $this->isEdit=false;
        }
        try {
            $goodsInfo = parent::buildData($goods);
            $this->parseGoodsInfo = $goodsInfo;
            //扩展属性
            $xinshangData['originalTime'] = "";
            $xinshangData['selfDefBrand'] = "";
            $xinshangData['serviceId'] = 0;
            $xinshangData['goodsPubType'] = 3;
            $xinshangData['sizeId'] = 0;
            foreach ($goodsInfo['extend_attribute'] as $attribute) {
                if ($attribute['attribute_meta']['xinshang_attribute_id']) {
                    if (in_array($attribute['attribute_meta']['attribute_type'], ['radio', 'checkbox'])) {
                        $value = array_map(function ($v) {
                            return $v['ponhu_attribute_id'];
                        }, $attribute['attribute_value']);
                        $xinshangData[$attribute['attribute_meta']['xinshang_attribute_id']] = implode("|", $value);
                    } else {
                        $attributes = end($attribute['attribute_value']);
                        $xinshangData[$attribute['attribute_meta']['xinshang_attribute_id']] = $attributes['attribute_value'];
                    }
                }
            }


            //售价
            if (empty($goodsInfo['price'])) {
                $this->error = 422;
                $this->errorMessage = '售价不能为空';
                return false;
            }
            $userInfo = $goods->user()->first();

            if ($userInfo->is_add_price == 1) {
                $diffprice = ($goodsInfo['price'] / 0.88) - $goodsInfo['price'];
                if ($diffprice <= 50) {
                    $price = $goodsInfo['price'] + 50;
                } else if ($diffprice >= 3000) {
                    $price = $goodsInfo['price'] + 300;
                } else {
                    $price = $goodsInfo['price'] / 0.88;
                }
                $xinshangData['publishPrice'] = $price;
            } else {
                $xinshangData['publishPrice'] = $goodsInfo['price'];
            }


            //售价
            if (empty($goodsInfo['fineness'])) {
                $this->error = 422;
                $this->errorMessage = '新旧程度不能为空';
                return false;
            }
            //新旧程度
            switch ($goodsInfo['fineness']) {
                case 100:
                    $xinshangData['usageStateId'] = "1";
                    break;
                case 99:
                    $xinshangData['usageStateId'] = "2";
                    break;
                case 98:
                    $xinshangData['usageStateId'] = "3";
                    break;
                case 95:
                    $xinshangData['usageStateId'] = "3";
                    break;
                case 90:
                    $xinshangData['usageStateId'] = "4";
                    break;
                case 85:
                    $xinshangData['usageStateId'] = "5";
                    break;
                case 80:
                    $xinshangData['usageStateId'] = "5";
                    break;
            }

            if (empty($goodsInfo['title'])) {

                $this->error = 422;

                $this->errorMessage = '标题不能为空';

                return false;
            }
            $xinshangData['publishTitle'] = $goodsInfo['title'];


            //描述
            if (empty($goodsInfo['description'])) {
                $this->error = 422;
                $this->errorMessage = '描述不能为空';
                return false;
            }
            $xinshangData['describe'] = $goodsInfo['description'];


            //分类id
            if (empty($goodsInfo['child_category']['xinshang_category_id'])) {
                $this->error = 422;
                $this->errorMessage = '子分类为空';
                return false;
            }
            $xinshangData['categoryId'] = $goodsInfo['child_category']['xinshang_category_id'];

            //品牌
            if (empty($goodsInfo['brand']['xinshang_brand_id'])) {
                $this->error = 422;
                $this->errorMessage = '品牌为空';
                return false;
            }
            $xinshangData['brandId'] = $goodsInfo['brand']['xinshang_brand_id'];


            //适用人群
            if (!isset($xinshangData['sex'])) {
                $this->error = 422;
                $this->errorMessage = '适用人群为空';
                return false;
            }


            //尺寸
            if (!isset($xinshangData['size']) && !isset($xinshangData['sizeId'])) {
                $this->error = 422;
                $this->errorMessage = '尺寸为空';
                return false;
            }
            $xinshangData['stockNum'] = $goodsInfo['number'];
            //寄回地址
            if (!isset($xinshangData['returnAddressId'])) {
                $this->error = 422;
                $this->errorMessage = '寄回地址为空';
                return false;
            }
            $xinshangData['purchasePrice'] = !isset($xinshangData['purchasePrice']) ? "" : $xinshangData['purchasePrice'];
            $xinshangData['accessories'] = !isset($xinshangData['accessories']) ? "" : $xinshangData['accessories'];
            $xinshangData['modelSerial'] = !isset($goodsInfo['goods_sn']) ? "" : $xinshangData['goods_sn'];
            //处理图片
            if (count($goodsInfo['photo_list']) < 4) {
                $this->error = 422;
                $this->errorMessage = '图片不能少于4张';
                return false;
            }

            //$i=0;
            foreach ($goodsInfo['photo_list'] as $key => $photo) {
                $result=base64_encode(file_get_contents($photo['path_url']));
                if ($result) {

                    $pathinfo = pathinfo($result);
                    $goods_images[] = $result;
                    $picUniqIds[] = $pathinfo['filename'];
                    $positionList[] = $key + 1;
                    $xinshangData['pics'] = implode(",", $goods_images);
                } else {
                    $this->error = 422;
                    $this->errorMessage = '上传图片失败,请重新上传';
                    return false;
                }

            }
            $xinshangData['timestamp'] = $this->getMicrotime();
            $xinshangData['sign'] = $this->makeSign($xinshangData);
            //dd($xinshangData);
            $this->goodsInfo = $xinshangData;
            return true;
        }
        catch(\Exception $e)
        {
            $this->error = 422;
            $this->errorMessage = '数据整理异常，请联系管理员';
            return false;
        }

    }


    public function send()
    {

        try
        {
            $url="http://api.91xinshang.com/user/goods/publish/apply";
            $response = $this->httpClient->post($url, ['form_params' => $this->goodsInfo]);
            $result=json_decode($response->getBody()->getContents(),true);
            if($result["code"]=="0"&&isset($result["data"]["tradeId"])) {
                if(!empty($this->shareHistory))
                {
                    $this->shareHistory->status=2;
                    $this->shareHistory->save();
                    $this->downGoods($this->shareHistory->platform_goods_id);
                }
                $goodsId=$result["data"]["tradeId"];
                $data['platform_id']=$this->platformAccount->platform_id;
                $data['platform_account_id']=$this->platformAccount->id;
                $data['platform_price']=$this->goodsInfo['publishPrice'];
                $data['platform_goods_id']=$goodsId;
                $data['user_id']=$this->goods->user_id;
                $data['status']=1;
                $data['from_data']=json_encode($this->parseGoodsInfo);
                unset($this->goodsInfo['pics']);
                $data['to_data']=json_encode($this->goodsInfo);
                $data['platform']=$this->platformAccount->platform;
                $this->goods->share_history()->create($data);
                return true;
            }
            else
            {
                $this->error=422;
                $this->errorMessage=$result['msg'];
                return false;
            }

        }
        catch (\Exception $e)
        {
            $this->error=422;
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }



    private function uploadFile($url)
    {
        try{
            $data['time']=$this->getMicrotime();
            $data['sign']=$this->makeSign($data);
            $uri="/file/upload";
            $response=$this->httpClient->post($uri, [
                'multipart' => [

                    [
                        'name'     => 'timestamp',
                        'contents' => $data['time']
                    ],
                    [
                        'name'     => 'sign',
                        'contents' => $data['sign']
                    ],
                    [

                        'name'     => 'img_base[0]',
                        'contents' => fopen($url, 'r'),
                        'filename' => '_1533026321004_90203_20167355.jpg'
                    ],
                ]]);
            $result = json_decode($response->getBody()->getContents(), true);
            if($result['code']==0)
            {
                return $result['data']['result'][0]['url'];
            }
            else{
                $this->error = 422;
                $this->errorMessage = $result['msg'];
                return false;
            }
        }
        catch(\Exception $e)
        {
            $this->error=422;
            $this->errorMessage='上传图片失败,请重新上传';
            return false;
        }


    }

    /**
     * @param $account 发送短信
     * @param int $code 区位码
     * @return bool 是否成功
     */
    public function sendSms($account, $code = 86)
    {

        try {
            $data['mobile'] = $account;
            $data['type'] = 1;
            $data['sendType'] = 0;
            $data['timestamp'] = $this->getMicrotime();
            $data['sign'] = $this->makeSign($data);
            $url = "/sms/verifyCode?" . http_build_query($data);
            $response = $this->httpClient->get($url);
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['code'] == 0) {
                return true;
            } else {
                $this->error = '422';
                $this->errorMessage = '获取短信失败，请联系管理员';
                return false;
            }
        } catch (\Exception $e) {
            $this->error = 422;
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }


    private function checkLogin($loginInfo)
    {
        try {
            $loginInfo = json_decode($loginInfo, true);
            $data['timestamp'] = $this->getMicrotime();
            $data['sign'] = $this->makeSign($data);
            $this->headers['sessionId'] = $loginInfo['sessionId'];
            $this->httpClient = new Client(['base_uri' => 'https://api.91xinshang.com/', 'headers' => $this->headers]);
            $response = $this->httpClient->post('/v2/order/mine', ['form_params' => $data]);
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['code'] == 0) {
                $this->loginInfo = $loginInfo;
                return true;
            } else {
                $this->error = 422;
                $this->errorMessage = $result['msg'];
                return false;
            }
        } catch (\Exception $e) {
            $this->error = 422;
            $this->errorMessage = $e->getMessage();
            return false;
        }
    }


    /**
     * @param $data  参与签名的参数
     * @return string 返回签名后的数据
     */
    private function makeSign($data)
    {
        ksort($data);
        $str = implode("", $data);
        return md5($str . "*GHHKJ&%");
    }

    /**
     * @return string 获取毫秒级的时间戳
     */
    private function getMicrotime()
    {
        $mtime = explode(' ', microtime());
        $mtime["0"] = (int)($mtime["0"] * 1000);
        return $mtime["1"] . $mtime["0"];
    }



    public function getReturnAddressId()
    {
        try {
            $url = "/user/address/list";
            $data['addrType'] = 0;
            $data['timestamp'] = $this->getMicrotime();
            $data['sign'] = $this->makeSign($data);
            $response = $this->httpClient->post($url, ['form_params' => $data]);
            $result = json_decode($response->getBody()->getContents(), true);
            if ($result['code'] == 0) {
                $temp=[];
                foreach($result['data']['result'] as $item)
                {
                    $temp['data'][]=['id'=>$item['addressId'],'address'=>$item['contactName']." ".$item['phone']." ".$item['city']." ".$item['address']];
                }
                return $temp;
            } else {
                $this->error = 422;
                $this->errorMessage = $result['msg'];
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->error = 422;
            $this->errorMessage = $e->getMessage();
            return false;
        }


    }



    public function downGoods($goodsId)
    {

        try
        {
            $data["goodsId"]=$goodsId;
            $data["timestamp"]=$this->getMicrotime();
            $data["sign"]=$this->makeSign($data);
            $response = $this->httpClient->post('/user/goods/onOff', ['form_params' => $data]);
            $result=json_decode($response->getBody()->getContents(),true);
            dd($result);
            if($result["code"]=="0")
            {
                return true;
            }
            else
            {
                $this->error=422;
                $this->errorMessage='下架失败';
                return false;
            }
        }
        catch (\Exception $e)
        {
            $this->error=422;
            $this->errorMessage=$e->getMessage();
            return false;
        }
    }



    public function getError()
    {

        return $this->error;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}