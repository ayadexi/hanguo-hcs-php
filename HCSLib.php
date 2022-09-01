<?php
enum HTTPRequestMethods: int
{
    case GET = 0;
    case POST = 1;
}

/**
 * 大韩民国教育部健康情况自家诊断程序库
 * MOE Health Check System library.
 * @author ayadexi
 * @version 1.9.11
 */
class HCS
{
    private static string $rspns00 = 'Y';
    
    private static string $rspns01 = '1';
    
    private static string $rspns02 = '1';
    
    private static string|null $rspns03 = '1';
    
    private static string|null $rspns07 = null;
    
    private static $rspns04, $rspns05, $rspns06, $rspns08, $rspns09, $rspns10, $rspns11, $rspns12, $rspns13, $rspns14, $rspns15 = null;

    private static array $headers = [
        'Content-Type: application/json',
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13B143',
        'Origin: https://hcs.eduro.go.kr',
        'Referer: https://hcs.eduro.go.kr/'
    ];

    const GET = HTTPRequestMethods::GET;
    const POST = HTTPRequestMethods::POST;
    
    /**
     * @param string $orgName   Name of organization
     * @param string $name      Name of user
     * @param string $birthday  Birthday of user (in YYMMDD format)
     * @param string $loginType Login type of user
     * @param string $region    Region of user
     * @param string $password  Login password of user
     */
    public function __construct(
        public string $orgName,
        public string $name,
        public string $birthday, 
        public string $loginType,
        public string $region,
        public string $password
    ) {}
    
    /**
    * RSA Encryption (RSA/ECB/PKCS1Padding)
    * @param  string $text  text to encrypt
    * @return string        encrypted text
    */
    private static function RSAEncrypt(string $text): string
    {
        $publicKey = 
            "-----BEGIN PUBLIC KEY-----\n".
            'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA81dCnCKt0NVH7j5Oh2+SGgEU0aqi5u6sYXemouJWXOlZO3jqDsHYM1qfEjVvCOmeoMNFXYSXdNhflU7mjWP8jWUmkYIQ8o3FGqMzsMTNxr+bAp0cULWu9eYmycjJwWIxxB7vUwvpEUNicgW7v5nCwmF5HS33Hmn7yDzcfjfBs99K5xJEppHG0qc+q3YXxxPpwZNIRFn0Wtxt0Muh1U8avvWyw03uQ/wMBnzhwUC8T4G5NclLEWzOQExbQ4oDlZBv8BM/WxxuOyu0I8bDUDdutJOfREYRZBlazFHvRKNNQQD2qDfjRz484uFs7b5nykjaMB9k/EJAuHjJzGs9MMMWtQIDAQAB'.
            "\n-----END PUBLIC KEY-----";
        openssl_public_encrypt($text, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);
        return base64_encode($encrypted);
    }

    /**
    * send HTTP request with curl
    * @param HTTPRequestMethods $method request method
    * @param string $url     request URL
    * @param array  $headers HTTP headers
    * @param array  $data    form data to submit
    * @return array|null     returns null when error
    */
    private static function request(HTTPRequestMethods $method, string $url, array $headers=[], array $data=[], $decode=1): array|string|null
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, 'https://'.$url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        // POST
        if($method->value === 1){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,JSON_UNESCAPED_UNICODE));
        }

        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if($method->value === 1){
            $headers = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            return [json_decode($body, true), $headers];
        }else
            return ($decode===1)?json_decode($response, true):$response;
        
    }

    /**
     * hcs 1.9.5 update:
     * get search key
     * -----------------
     * hcs 1.9.10 update:
     * get crypted orgCode
     */
    public function getSchoolInfo(): void
    {
        $get = self::request(self::GET, 'hcs.eduro.go.kr/v2/searchSchool?orgName='.urlencode($this->orgName));
        
        foreach($get['schulList'] as $sc){
            if($sc['kraOrgNm'] == $this->orgName && $sc['lctnScNm'] == $this->region){
                $this->orgCode = $sc['orgCode'];
                $this->lctnScCode = $sc['lctnScCode'];
                $this->juOrgCode = $sc['juOrgCode'];
                $this->baseUrl = $sc['atptOfcdcConctUrl'];
                $this->searchKey = $get['key'];
            }else
                continue;
        }
        
        if(!isset($this->orgCode))
            die('school not found');
    }

    /**
     * find user data
     * -----------------
     * hcs 1.9.10 update:
     * change endpoint to /v3/
    */
    public function findUser(): void
    {
        require __DIR__.'/TransKey.php';

        $this->getSchoolInfo();

        $raon = new Transkey($this->password);
        
        $data = [
            'orgCode' => $this->orgCode,
            'name' => self::RSAEncrypt($this->name),
            'birthday' => self::RSAEncrypt($this->birthday),
            'loginType' => $this->loginType,
            'stdntPNo' => null,
            'password' => json_encode($raon->json),
            'lctnScCode' => $this->lctnScCode,
            'deviceUuid' => '',
            'makeSession' => true,
            'searchKey' => $this->searchKey
        ];

        $res = self::request(self::POST, $this->baseUrl.'/v3/findUser', self::$headers, $data);
        $this->stdntYn = $res[0]['stdntYn'];
        $this->token = $res[0]['token'];
        $this->pInfAgrmYn = $res[0]['pInfAgrmYn'];
        $this->WAF = substr($res[1], strpos($res[1], 'WAF='), 37);
        $this->_JSESSIONID = substr($res[1], strpos($res[1], '_JSESSIONID='), 121);
    
        //if($res[0]['isError'])
        //    throw new ErrorException($res[0]['statusCode'].'/'.$res[0]['errorCode'].':'.$res[0]['message']);
    }
    
    /**
    * get registered user list and change token
    */
    public function selectUserGroup(): void
    {
        self::$headers[4] = 'Authorization: '.$this->token;
        self::$headers[5] = 'Cookie: '.$this->WAF.$this->_JSESSIONID;
        
        $data = [];
        
        $res = self::request(self::POST, $this->baseUrl.'/v2/selectUserGroup', self::$headers, $data)[0];
        
        $this->token = $res[0]['token'];
        $this->userPNo = $res[0]['userPNo'];
        
        self::$headers[4] = 'Authorization: '.$this->token;
    }
    
    /**
    * get user details
    */
    public function getUserInfo(): void
    {
        $data = [
            'orgCode' => $this->juOrgCode,
            'userPNo' => $this->userPNo
        ];
        
        $res = self::request(self::POST, $this->baseUrl.'/v2/getUserInfo', self::$headers, $data);
    }
    
    /**
    * get hcs client version
    * @return string hcs client version
    */
    public static function getClientVersion(): string
    {
        file_get_contents('https://hcs.eduro.go.kr/');
        foreach($http_response_header as $header){
            if(str_starts_with($header, 'X-Client-Version')){
                $version = str_replace('X-Client-Version: ', '', $header);
                return $version;
            }
        }

        return 'Cannot find version';
    }
    
    /**
    * submit health check survey content
    * @return array|null returns null when error
    */
    public function registerServey(): array|null
    {
        $data = [
            'rspns00' => self::$rspns00,
            'rspns01' => self::$rspns01,
            'rspns02' => self::$rspns02,
            'rspns03' => self::$rspns03,
            'rspns04' => self::$rspns04,
            'rspns05' => self::$rspns05,
            'rspns06' => self::$rspns06,
            'rspns07' => self::$rspns07,
            'rspns08' => self::$rspns08,
            'rspns09' => self::$rspns09,
            'rspns10' => self::$rspns10,
            'rspns11' => self::$rspns11,
            'rspns12' => self::$rspns12,
            'rspns13' => self::$rspns13,
            'rspns14' => self::$rspns14,
            'rspns15' => self::$rspns15,
            'upperToken' => $this->token,
            'upperUserNameEncpt' => $this->name,
            'deviceUuid' => '',
            'clientVersion' => self::getClientVersion()
        ];
        
        return self::request(self::POST, $this->baseUrl.'/registerServey', self::$headers, $data);
    }
    
    /*
    public function searchSchool()
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$this->token,
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13B143',
            'Origin: https://hcs.eduro.go.kr',
            'Referer: https://hcs.eduro.go.kr/'
        ];
        
        $data = [
            'orgCode' => $orgCode,
            'userPNo' => $userPNo
        ]; 
        
        return self::requestPOST($url, $headers, $data);
    }
    
    public function joinClassList()
    {
        
        $data = [];
        
        return self::requestPOST($this->region.'hcs.eduro.go.kr/joinClassList', self::$headers, $data);
    }
    
    public function join($token, $orgCode, $grade, $classNm, $classCode, $url)
    {
        $data = [
            'orgCode' => $this->orgCode,
            'grade' => $this->grade,
            'classNm' => $this->classNm,
            'classCode' => $this->classCode
        ];
        
        return self::requestPOST($this->region.'hcs.eduro.go.kr/join', self::$headers, $data);
    }
    
    public function joinDetail($data)
    {
        return self::requestPOST($this->region.'hcs.eduro.go.kr/joinDetail', self::$headers, $data);
    }*/
}
