<?php
require 'HCSLib.php';

// 应该用韩文的学校名, 名字, 市道名
$HCS = new HCS('한국고등학교', '홍길동', '040101', 'school', '서울특별시', '1234');

$HCS->findUser();
$HCS->selectUserGroup();
$res = HCS->registerServey();

if($res[0]['registerDtm'])
    echo '自家诊断完了';
