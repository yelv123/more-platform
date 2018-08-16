<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/19 0019
 * Time: 13:30
 */
namespace MorePlatform;
class MorePlatformClient
{
    public $platformClient;
    private $error='';
    private $errorMessage='';
    public function __construct()
    {


    }

    /**
     * @param Platform $platformAccount 登录接口
     * $force 是否强制登录
     */
    public function login($platformAccount,$force=false)
    {
        $platform="\\MorePlatform\\Platform\\".ucfirst($platformAccount->platform);
        $this->platformClient=new $platform();
        $result=$this->platformClient->login($platformAccount,$force);
        if($result)
        {
            return $this->platformClient;
        }
        else{
            $this->error=$this->platformClient->getError();
            $this->errorMessage=$this->platformClient->getErrorMessage();
            return false;
        }

    }

    /**
     * @param Platform $platformAccount 发送短信
     */
    public function sendSms($platform,$account,$code=86)
    {

        $platform="\\MorePlatform\\Platform\\".ucfirst($platform->platform);
        $this->platformClient=new $platform();
        $result=$this->platformClient->sendSms($account,$code);
        if($result)
        {
            return true;
        }
        else{
            $this->error=$this->platformClient->getError();
            $this->errorMessage=$this->platformClient->getErrorMessage();
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