<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Bus extends CI_Controller {

    const x_PI  = 52.35987755982988;
    const PI  = 3.1415926535897932384626;
    const a = 6378245.0;
    const ee = 0.00669342162296594323;

    var $lbskey = '';

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */

    private function _getFloatLength($num) {
        $count = 0;
        $temp = explode ( '.', $num );
        if (sizeof ( $temp ) > 1) {
            $decimal = end ( $temp );
            $count = strlen ( $decimal );
        }
        return $count;
    }

    public function likeQuery()
    {
        $site = $this->input->post_get('site');
        $response = $this->_post_json('http://weixin.hfbus.cn/HFRTB/likeQuery', [
            'site' => $site
        ]);
        return $this->json($response);
    }

	public function nearby()
	{
        $lat = floatval($this->input->post_get('lat'));
        $lng = floatval($this->input->post_get('lng'));

        $resolved = $this->gcj02towgs84($lng, $lat);
        $lat = $resolved[1];
        $lng = $resolved[0];

        if ($this->_getFloatLength($lat) < 6) {
            $lat += 0.000001;
        }

        if ($this->_getFloatLength($lng) < 6) {
            $lng += 0.000001;
        }

        $response = $this->_post_json('http://weixin.hfbus.cn/HFRTB/nearLineQuery', [
            'lat' => number_format($lat, 6, '.', ''),
            'lng' => number_format($lng, 6, '.', '')
        ]);
        return $this->json($response);
    }

    public function site()
	{
        $flag = $this->input->post_get('flag');
        $linename = $this->input->post_get('linename');
        $response = $this->_post_json('http://weixin.hfbus.cn/HFRTB/siteAndResult', [
            'flag' => $flag,
            'linename' => $linename
        ]);

        $coords = array();
        $coords2 = array();

        foreach ($response['data']['list'][0]['stationlist'] as $k => $v) {
            array_push($coords, $v['WD'].','.$v['JD']);
        }
        foreach ($response['data']['list'][1]['stationlist'] as $k => $v) {
            array_push($coords2, $v['WD'].','.$v['JD']);
        }

        $res1 = $this->_get_json('http://apis.map.qq.com/ws/coord/v1/translate?locations='.implode(';', $coords).'&type=1&key='.$this->lbskey);
        $res2 = $this->_get_json('http://apis.map.qq.com/ws/coord/v1/translate?locations='.implode(';', $coords2).'&type=1&key='.$this->lbskey);

        if ($res1['status'] == 0) {
            foreach ($res1['locations'] as $k1 => $v1) {
                $response['data']['list'][0]['stationlist'][$k1]['WD'] = $v1['lat'];
                $response['data']['list'][0]['stationlist'][$k1]['JD'] = $v1['lng'];
            }
        }

        if ($res2['status'] == 0) {
            foreach ($res2['locations'] as $k2 => $v2) {
                $response['data']['list'][1]['stationlist'][$k2]['WD'] = $v2['lat'];
                $response['data']['list'][1]['stationlist'][$k2]['JD'] = $v2['lng'];
            }
        }

        return $this->json($response);
    }

    private function _post_json($url, $post_fields)
    {
        $connection = curl_init();
        curl_setopt_array($connection, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => http_build_query($post_fields),
            CURLOPT_REFERER => 'http://weixin.hfbus.cn/HFRTB/',
            CURLOPT_HTTPHEADER => array(
                'X-Requested-With' => 'XMLHttpRequest',
                'Host' => 'weixin.hfbus.cn',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0',
            )
        ]);
        $response = curl_exec($connection);
        curl_close($connection);
        return json_decode($response, true);
    }

    private function _get_json($url)
    {
        $connection = curl_init();
        curl_setopt_array($connection, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HEADER => 0,
            CURLOPT_POST => 0,
            CURLOPT_HTTPHEADER => array(
                'X-Requested-With' => 'XMLHttpRequest',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0',
            )
        ]);
        $response = curl_exec($connection);
        curl_close($connection);
        return json_decode($response, true);
    }

    public function wgs84togcj02($lng,  $lat) {
        if ($this->out_of_china($lng, $lat)) {
            return array($lng, $lat);
        } else {
            $dlat = $this->transformlat($lng - 105.0, $lat - 35.0);
            $dlng = $this->transformlng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * self::PI;
            $magic = sin($radlat);
            $magic = 1 - self::ee * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((self::a * (1 - self::ee)) / ($magic * $sqrtmagic) * self::PI);
            $dlng = ($dlng * 180.0) / (self::a / $sqrtmagic * cos($radlat) * self::PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return array($mglng, $mglat);
        }
    }
    /**
     * GCJ02 转换为 WGS84 (高德转北斗)
     * @param lng
     * @param lat
     * @return array(lng, lat);
     */
    public function gcj02towgs84($lng, $lat) {
        if ($this->out_of_china($lng, $lat)) {
            return array($lng, $lat);
        } else {
            $dlat = $this->transformlat($lng - 105.0, $lat - 35.0);
            $dlng = $this->transformlng($lng - 105.0, $lat - 35.0);
            $radlat = $lat / 180.0 * self::PI;
            $magic = sin($radlat);
            $magic = 1 - self::ee * $magic * $magic;
            $sqrtmagic = sqrt($magic);
            $dlat = ($dlat * 180.0) / ((self::a * (1 - self::ee)) / ($magic * $sqrtmagic) * self::PI);
            $dlng = ($dlng * 180.0) / (self::a / $sqrtmagic * cos($radlat) * self::PI);
            $mglat = $lat + $dlat;
            $mglng = $lng + $dlng;
            return array($lng * 2 - $mglng, $lat * 2 - $mglat);
        }
    }


        /**
    　　* 百度坐标系 (BD-09) 与 火星坐标系 (GCJ-02)的转换
    　　* 即 百度 转 谷歌、高德
    　　* @param bd_lon
    　　* @param bd_lat
    　　* @returns
　　*/
        public function bd09togcj02 ($bd_lon, $bd_lat) {
            $x_pi = 3.14159265358979324 * 3000.0 / 180.0;
            $x = $bd_lon - 0.0065;
            $y = $bd_lat - 0.006;
            $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
            $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
            $gg_lng = $z * cos(theta);
            $gg_lat = $z * sin(theta);
            return array($gg_lng, $gg_lat);
        }

    /**
    * GCJ-02 转换为 BD-09  （火星坐标系 转百度即谷歌、高德 转 百度）
    * @param $lng
    * @param $lat
    * @returns array(bd_lng, bd_lat)
    */
    public function gcj02tobd09($lng, $lat) {
        $z = sqrt($lng * $lng + $lat * $lat) + 0.00002 * Math.sin($lat * x_PI);
        $theta = Math.atan2($lat, $lng) + 0.000003 * Math.cos($lng * x_PI);
        $bd_lng = $z * cos($theta) + 0.0065;
        $bd_lat = z * sin($theta) + 0.006;
        return array($bd_lng, $bd_lat);
    }


    private function transformlat($lng, $lat) {
        $ret = -100.0 + 2.0 * $lng + 3.0 * $lat + 0.2 * $lat * $lat + 0.1 * $lng * $lat + 0.2 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * self::PI) + 20.0 * sin(2.0 * $lng * self::PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lat * self::PI) + 40.0 * sin($lat / 3.0 * self::PI)) * 2.0 / 3.0;
        $ret += (160.0 * sin($lat / 12.0 * self::PI) + 320 * sin($lat * self::PI / 30.0)) * 2.0 / 3.0;
        return $ret;
    }
    private function transformlng($lng, $lat) {
        $ret = 300.0 + $lng + 2.0 * $lat + 0.1 * $lng * $lng + 0.1 * $lng * $lat + 0.1 * sqrt(abs($lng));
        $ret += (20.0 * sin(6.0 * $lng * self::PI) + 20.0 * sin(2.0 * $lng * self::PI)) * 2.0 / 3.0;
        $ret += (20.0 * sin($lng * self::PI) + 40.0 * sin($lng / 3.0 * self::PI)) * 2.0 / 3.0;
        $ret += (150.0 * sin($lng / 12.0 * self::PI) + 300.0 * sin($lng / 30.0 * self::PI)) * 2.0 / 3.0;
        return $ret;
    }


    private function rad($param)
    {
      return  $param * self::PI / 180.0;
    }
    /**
    * 判断是否在国内，不在国内则不做偏移
    * @param $lng
    * @param $lat
    * @returns {boolean}
    */
    private function out_of_china($lng, $lat) {
        return ($lng < 72.004 || $lng > 137.8347) || (($lat < 0.8293 || $lat > 55.8271) || false);
    }


}
