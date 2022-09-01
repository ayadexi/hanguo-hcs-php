<?php
require __dir__.'/SEED_CBC_HCS.php';

class TransKey
{
    private static string $delimiter = '$';
    private static string $token;
    private static array $keysXY = [
        [125, 27], [165, 27], [165, 67], [165, 107],
        [165, 147], [125, 147], [85, 147], [45, 147],
        [45, 107], [45, 67], [45, 27], [85, 27]
    ];

    public function __construct(string $password, $debug=false, $debugData=[])
    {
        $transkeyServlet = 'https://hcs.eduro.go.kr/transkeyServlet';

        // requestToken
        $getToken = self::fetch($transkeyServlet.'?op=getToken');
        preg_match('/TK_requestToken=\'?([0-9a-fA-F]*)\'?;/', $getToken, $rTmatch);
        $token = $rTmatch[1];

        // session key
        $genSessionKey = bin2hex(random_bytes(16));
        $this->sessionKey = array_map('hexdec', str_split($genSessionKey));

        $certificate = self::fetch($transkeyServlet, http_build_query([
            'op' => 'getPublicKey',
            'TK_requestToken' => $token
        ]));
        $publicKey = openssl_pkey_get_public(openssl_x509_read(
            "-----BEGIN CERTIFICATE-----\n".
            $certificate.
            "\n-----END CERTIFICATE-----"
        ));
        
        openssl_public_encrypt($genSessionKey, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING);
        $encSessionKey = bin2hex($encrypted);
        
        // get initTime
        $getInitTime = self::fetch($transkeyServlet.'?op=getInitTime');
        preg_match('/ initTime=\'([0-9a-fA-F]*)\'/', $getInitTime, $iTmatch);
        //preg_match('/ decInitTime=\'([0-9]*)\'/', $getInitTime, $dITmatch);
        $this->initTime = $iTmatch[1];
        //$this->decInitTime = $dITmatch[1];

        $keyIndex = self::fetch($transkeyServlet, http_build_query([
            'op' => 'getKeyIndex',
            'name' => 'password',
            'keyboardType' => 'number',
            'initTime' => $this->initTime
            /*'keyType' => 'single',
              'fieldType' => 'password',
              'inputName' => 'password',
              'parentKeyboard' => 'false',
              'transkeyUuid' => $this->uuid,
              'exE2E' => 'false',
              'TK_requestToken' => $token,
              'isCrt' => 'false',
              'allocationIndex' => '3011907012',
              'keyIndex' => '',
              'talkBack' => 'true'
            */
        ]));

        $this->keys = explode(',', self::fetch($transkeyServlet, http_build_query([
            'op' => 'getDummy',
            'keyboardType' => 'number',
            'fieldType' => 'password',
            'keyIndex' => $keyIndex,
            'talkBack' => 'true'
            /*'name' => $name,
              'keyType' => 'single',
              'inputName' => $inputName,
              'transkeyUuid' => $this->uuid,
              'exE2E' => 'false',
              'isCrt' => 'false',
              'allocationIndex' => '3011907012',
              'initTime' => $this->initTime,
              'TK_requestToken' => $token,
              'dummy' => 'undefined',
            */
        ])));

        $enc = implode('', array_map(function($n) {
            list($x, $y) = self::$keysXY[array_search($n, $this->keys)];
            return self::$delimiter . SEED::encrypt($x.' '.$y, $this->sessionKey, $this->initTime);
        }, str_split($password)));
        
        for ($j=4; $j<128; $j++) {
            $enc .= self::$delimiter . SEED::encrypt('# 0 0', $this->sessionKey, $this->initTime);
        }

        $hmac = hash_hmac('sha256', $enc, $genSessionKey);

        // get keyinfo
        /*$this->keyInfo = self::fetch($transkeyServlet, http_build_query([
            'op' => 'getKeyInfo',
            'key' => $this->key,
            'transkeyUuid' => $this->uuid,
            'useCert' => 'true',
            'TK_requestToken' => $token,
            'mode' => 'common'
        ]));
        */

        $this->json = [
            'raon' => [[
                'id' => 'password',
                'enc' => $enc,
                'hmac' => $hmac,
                'keyboardType' => 'number',
                'keyIndex' => $keyIndex,
                'fieldType' => 'password',
                'seedKey' => $encSessionKey,
                //'initTime' => $this->initTime,
                //'ExE2E' => 'false'
            ]]
        ];
    }

    private static function fetch($url, $body = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13B143',
            'Origin: https://hcs.eduro.go.kr',
            'Referer: https://hcs.eduro.go.kr/'
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}
