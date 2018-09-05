<?php

namespace Weiler\UploadImg;

class UploadImg
{
    protected $src;
    protected $msg;
    protected $extension;
    protected $data;
    protected $dst;


    public $savePath;       //文件保存路径
    public $SourceName;     //源文件保存名
    public $thumb;          //缩略图保存名
    public $width = 200;          //保存图片的宽度
    public $height = 200;         //保存图片的高度
    public $limit = 3000;   //限制最大输入宽高

    /**
     * 初始化
     *
     * @return void
     */
    public function __construct()
    {
        $this->savePath = 'UploadFiles/';
        $this->SourceName = date('YmdHis');
        $this->thumb = 't_'.date('YmdHis').'.png';
    }

    protected function setData($data) {
        if (!empty($data)) {
            $this -> data = json_decode(stripslashes($data));
        }
    }

    protected function setDst()
    {
        $this->dst = $this->savePath.$this->thumb;
    }

    /**
     * 图片源文件
     */
    protected function setImgSource($file)
    {
        $extension = $file->guessExtension();
        if ($extension == 'jpeg' || $extension == 'gif' || $extension == 'png')
        {
            $src = $this->savePath.$this->SourceName.'.'.$extension;
            if (file_exists($src)) {
                unlink($src);
            }

            if ($file->move($this->savePath, $this->SourceName.'.'.$extension))
            {
                $this->src = $src;
                $this->extension = $extension;
            }
        }else{
            $this -> msg = 'Please upload image with the following types: JPG, PNG, GIF';
        }
    }

    /**
     * 保存缩略图
     */
    protected function setThumb($data, $src, $dst)
    {
        //防止画布参数溢出
        $this->limitSize();
        switch ($this->extension) {
            case 'gif':
                $src_img = imagecreatefromgif($src);
                break;
            case 'jpeg':
                $src_img = imagecreatefromjpeg($src);
                break;
            case 'png':
                $src_img = imagecreatefrompng($src);
                break;
        }

        if (!$src_img) {
            $this -> msg = "Failed to read the image file";
            if (file_exists($src)) {
                unlink($src);
            }
            return;
        }

        $size = getimagesize($src);
        $size_w = $size[0]; // natural width
        $size_h = $size[1]; // natural height

        $src_img_w = $size_w;
        $src_img_h = $size_h;

        $degrees = $data->rotate;

        // Rotate the source image
        if (is_numeric($degrees) && $degrees != 0) {
            // PHP's degrees is opposite to CSS's degrees
            $new_img = imagerotate( $src_img, -$degrees, imagecolorallocatealpha($src_img, 0, 0, 0, 127) );

            imagedestroy($src_img);
            $src_img = $new_img;

            $deg = abs($degrees) % 180;
            $arc = ($deg > 90 ? (180 - $deg) : $deg) * M_PI / 180;

            $src_img_w = $size_w * cos($arc) + $size_h * sin($arc);
            $src_img_h = $size_w * sin($arc) + $size_h * cos($arc);

            // Fix rotated image miss 1px issue when degrees < 0
            $src_img_w -= 1;
            $src_img_h -= 1;
        }

        $tmp_img_w = $data->width;
        $tmp_img_h = $data->height;
        $dst_img_w = $this->width;
        $dst_img_h = $this->height;

        $src_x = $data->x;
        $src_y = $data->y;

        if ($src_x <= -$tmp_img_w || $src_x > $src_img_w) {
            $src_x = $src_w = $dst_x = $dst_w = 0;
        } else if ($src_x <= 0) {
            $dst_x = -$src_x;
            $src_x = 0;
            $src_w = $dst_w = min($src_img_w, $tmp_img_w + $src_x);
        } else if ($src_x <= $src_img_w) {
            $dst_x = 0;
            $src_w = $dst_w = min($tmp_img_w, $src_img_w - $src_x);
        }

        if ($src_w <= 0 || $src_y <= -$tmp_img_h || $src_y > $src_img_h) {
            $src_y = $src_h = $dst_y = $dst_h = 0;
        } else if ($src_y <= 0) {
            $dst_y = -$src_y;
            $src_y = 0;
            $src_h = $dst_h = min($src_img_h, $tmp_img_h + $src_y);
        } else if ($src_y <= $src_img_h) {
            $dst_y = 0;
            $src_h = $dst_h = min($tmp_img_h, $src_img_h - $src_y);
        }

        // Scale to destination position and size
        $ratio = $tmp_img_w / $dst_img_w;
        $dst_x /= $ratio;
        $dst_y /= $ratio;
        $dst_w /= $ratio;
        $dst_h /= $ratio;

        $dst_img = imagecreatetruecolor($dst_img_w, $dst_img_h);

        // Add transparent background to destination image
        imagefill($dst_img, 0, 0, imagecolorallocatealpha($dst_img, 0, 0, 0, 127));
        imagesavealpha($dst_img, true);

        $result = imagecopyresampled($dst_img, $src_img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);

        if ($result) {
            if (!imagepng($dst_img, $dst)) {
                $this -> msg = "Failed to save the cropped image file";
            }
        } else {
            $this -> msg = "Failed to crop the image file";
        }
        imagedestroy($src_img);
        imagedestroy($dst_img);
    }

    public function getResult() {
        return !empty($this -> data) ? $this -> dst : $this -> src;
    }

    public function getMsg() {
        return $this -> msg;
    }

    // 限制大小
    protected function limitSize()
    {
        $width      =   (int)$this->width;
        $height     =   (int)$this->height;
        if (max($width,$height) > $this->limit)
        {
            if($width > $height)
            {
                $ratio = $width/$this->limit;
                $this->width    =   $this->limit;
                $this->height   =   $height/$ratio;
            }else{
                $ratio = $height/$this->limit;
                $this->height   =   $this->limit;
                $this->width    =   $width/$ratio;
            }
        }
    }

    /**
     * 上传缩略图
     * $aspectRatio 数组存在时则为保存多个不同大小的图片
     */
    public function upThumb($file, $data, $aspectRatio = null)
    {
        $this->setData($data);
        $this->setImgSource($file);
        if(!empty($aspectRatio))
        {
            foreach($aspectRatio as $v)
            {
                $this->width = $v['width'];
                $this->height= $v['height'];
                $this->thumb = $v['path'];
                $this->setDst();
                $this->setThumb($this->data, $this->src, $this->dst);
            }
        }else{
            $this->setDst();
            $this->setThumb($this->data, $this->src, $this->dst);
        }

        if (file_exists($this->src)) {
            unlink($this->src);
        }

        $response = [
            'result'    =>  'OK',
            'code'      =>  200,
            'msg'       =>  'Successful',
            'data'      =>  [
                'path'  =>  $this->getResult(),
                'name'  =>  $this->thumb
            ]
        ];

        return $response;
    }

}
