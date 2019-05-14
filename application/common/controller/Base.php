<?php

namespace app\common\controller;


use think\cache\driver\Redis;
use think\Controller;
use PHPExcel_Style_Alignment;

class Base extends Controller
{
    const SUCCESS_200   = 200; // 成功
    const ERROR_400     = 400; // 参数错误
    const ERROR_403     = 403; // 没有权限
    const ERROR_404     = 404; // 返回值为空
    const ERROR_500     = 500; // 服务器内部错误
    const ERROR_777     = 777; // 用户未登录
    const ERROR_999     = 999; // 错误的登录信息

    // redis
    private $redis;
    protected $suffix = 'tissue';

    /**
     * Base constructor.
     */
    public function __construct()
    {
        parent::__construct();
      $this->redis = new Redis();
    }

    /**
     * 设置redis
     * @param $key
     * @return mixed
     * user : yfg
     * date : 2019-02-26
     */
    protected function getRedis($key)
    {
        return $this->redis->get($key.$this->suffix);
    }



    /**
     * 生成TOKEN
     * @return string
     * user : yfg
     * date : 2019-02-26
     */
    protected function generateToken()
    {
        return md5(time() . '_' . mt_rand(100000, 999999));
    }

    /**
     * 检测用户是否登录
     * @param $id
     * @param $token
     * @return bool
     * user : yfg
     * date : 2019-02-26
     */
    public function checkUserToken($id, $token)
    {
        if($token==$this->getRedis($id)){
            return true;
        }
        return false;
    }

    /**
     * 统一返回json
     * @param $code
     * @param string $msg
     * @param array $data
     * @return false|string
     * user : yfg
     * date : 2019-02-26
     */
    protected function resultJsonUtil($code, $msg = '', $data = [])
    {
        if (empty($msg)) {
            switch ($code) {
                case 200:
                    $msg = '成功';
                    break;
                case 400:
                    $msg = '参数错误';
                    break;
                case 403:
                    $msg = '没有权限';
                    break;
                case 404:
                    $msg = '返回值为空';
                    break;
                case 500:
                    $msg = '服务器内部错误';
                    break;
                case 777:
                    $msg = '请先登录再执行该操作';
                    break;
                case 999:
                    $msg = '非法登录';
                    break;
                default:
                    $msg = '错误信息';
            }
        }
        $returnData['code'] = $code;
        $returnData['msg'] = $msg;
        $returnData['data'] = $data;
        return json_encode($returnData, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 密码盐值加密
     * @param $password
     * @param $salt
     * @return string
     * user : yfg
     * date : 2019-02-26
     */
    protected function passwordEncryption($password, $salt)
    {
        $passwordEncryption = sha1(md5($password).$salt);
        return $passwordEncryption;
    }

    /**
     * @return string
     * user : yfg
     * date : 2019-02-26
     */
    protected function generateSalt()
    {
        $output='';
        for ($a = 0; $a<8; $a++) {
            $output .= chr(mt_rand(33, 126));
        }
        return $output;
    }

    /**
     * 上传单张图片
     * @Author: BuK
     * @Date: 2019-02-15 11:22
     * @param $file
     * @param $uploadDir
     * @return false|string
     */
    protected function uploadImgBase($file, $uploadDir)
    {
        $uploadDir = trim($uploadDir, '/');

        // 上传 - 移动到框架应用根目录/uploads/ 目录下
        $info = $file
            ->validate(['ext'=>'bmp,jpg,jpeg,png,gif'])
            ->move( '../public/'.$uploadDir);
        // 成功上传后 获取上传信息
        if($info) {
            // 输出 42a79759f284b767dfcb2a0197904287.jpg
            $imgUrl = $uploadDir.'/'.$info->getSaveName();

            return $this->resultJsonUtil(self::SUCCESS_200, '上传成功', $imgUrl);
        } else {
            // 上传失败获取错误信息
            $error = $file->getError();

            return $this->resultJsonUtil(self::ERROR_500, '上传失败', $error);
        }
    }

    /**
     * 删除文件[指定路径]
     * @Author: BuK
     * @Date: 2019-02-14 17:12
     * @param $fieldDir
     * @param int $limit
     * @return bool
     */
    protected function deleteUploadFieldBase($fieldDir, $limit = 3)
    {
        $fieldDir = trim($fieldDir, '/');

        $dir = '../public/'.$fieldDir;

        if (is_dir($dir)) {
            // 先删除目录下的文件
            $dirPath = opendir($dir);
            while ($file = readdir($dirPath)) {
                if($file != "." && $file != "..") {
                    $filePath = $dir.'/'.$file;
                    if(!is_dir($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            closedir($dirPath);

            // 删除当前文件夹
            if(rmdir($dir)) {
                return true;
            } else {
                if ($limit > 0) {
                    $this->deleteUploadImgBase($fieldDir, $limit-1);
                }
            }
        }
        return false;
    }

    /**
     * 保存用户Token
     * @param $id
     * @return string
     * user : yfg
     * date : 2019-01-31
     */
    public function saveUserToken($id)
    {
        $token = $this->generateToken();
        $this->setRedis($id, $token);
        return $token;
    }

    /**
     * 导出Excel
     * @author: hjl
     * @date: 2019/3/4 9:21
     * @param $field 字段
     * @param $list 对应的数据
     * @param $title 表格标题
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function outputExcel($field,$list,$title)
    {
        $objPHPExcel = new \PHPExcel();
        $objWriter = new \PHPExcel_Writer_Excel5($objPHPExcel); //设置保存版本格式

        foreach ($list as $key => $value) {
            foreach ($field as $k => $v) {
                if ($key == 0) {
                    $objPHPExcel->getActiveSheet()->setCellValue($k . '1', $v[1]);
                }
                $i = $key + 2; //表格是从2开始的
                $objPHPExcel->getActiveSheet()->setCellValue($k . $i, $value[$v[0]]);
                //表格居中
                $objPHPExcel->getDefaultStyle()->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $objPHPExcel->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
            }

        }
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename='.$title.date('Y-m-d H:i:s').'.xls');
        header("Content-Transfer-Encoding:binary");
        //直接从浏览器下载
        $objWriter->save('php://output');
    }
}
