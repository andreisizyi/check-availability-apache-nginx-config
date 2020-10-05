<?php
// Изначально думал об использовании 451 ошибки — недоступно по юридическим причинам, но к сожалению почти не один из бесплатных прокси, что нашел, не отдает такого ответа.
// Реального функционала проверки добиться не удалось, так как многие прокси Украины (Киев, Одесса) или открывают тот же Яндекс или отдают заглушку с 200.
class CheckUrl {
    // Задаю массивы прокси для каждой страны для точности результата, так как они работают нестабильно и одного недостаточно.
    private const USproxy = [
        '168.169.96.2:8080',
        '165.225.32.116:10223',
        '165.225.32.118:8800',
        '165.225.32.110:10417',
        '165.225.32.111:9400',
        '165.225.32.113:8800',
        '165.225.32.109:10223',
        '165.225.32.108:10965',
        '165.225.32.115:10223',
        '35.185.16.35:80',
        '165.225.32.107:10801',
        '206.125.41.130:80',
        '152.26.66.140:3128',
        '157.245.182.16:8080',
        '3.8.132.246:8080',
        '165.225.32.114:10417',
        '165.225.32.117:10356',
        '208.115.237.110:8080'
    ];  
    private const RUproxy = [
        '185.20.224.239:3128',
        '185.22.63.49:3128',
        '5.167.21.80:8080',
        '62.16.40.228:8080',
        '84.22.137.229:8080',
        '94.28.93.26:8080',
        '5.250.168.36:8080',
        '178.57.106.6:8080',
        '62.112.118.14:8080',
        '62.76.7.132:3128'
    ];
    private const UAproxy = [
        '134.249.134.41:3128',
        '92.244.99.229:3128',
        '194.28.68.115:8081',
        '212.66.61.118:37141',
        '94.179.135.230:43033',
        '178.216.2.229:8080',
        '46.63.71.13:8080',
        '46.219.80.142:57401',
        '37.57.216.2:2222',
        '195.60.174.123:39635',
        '134.249.141.148:8080',
        '195.138.82.198:40301'
    ];

    private $storage;
    // $proxyauth = 'user:password'; //Оставил на случай если нужно прокси с лог/пасом

    public function __construct() {
        $this->storage = $_REQUEST;
  
        // Вибираю согласно данных формы нужный массив прокси
        switch ($this->storage['country']) {
            case 'US':
                $proxys = self::USproxy;
                break;
            case 'UA':
                $proxys = self::UAproxy;
                break;
            case 'RU':
                $proxys = self::RUproxy;
                break;
        }
        
        //Получаю URL с формы и преобрузую в вид с различным протоколом для дальнейшей проверки на наличие успешного ответа 200 (на случай, если  сайта введен без указания протокола или неправильным)
        $url = $this->storage['url'];
        if(parse_url($url, PHP_URL_SCHEME) != 'http' || parse_url($url, PHP_URL_SCHEME) != 'https') {
            $part_url = strtolower(parse_url($url, PHP_URL_HOST).parse_url($url, PHP_URL_PATH));
            $url = 'https://'.$part_url;
            $url2 = 'http://'.$part_url;
        } else {
            $url = 'https://'.$url;
            $url2 = 'http://'.$url;
        }

        //Устанавливаю пределы Таймаута
        $time = $this->storage['time'];
        if ($time==0 || $time>20 || $time<20) {
            $time = 5;
        }

        //Добавляю в массив данные с url
        $urls[] = $url;
        $urls[] = $url2;

        // Создаю набор cURL-дескрипторов для паралельной обработки адреса прокси серверами
        $multi = curl_multi_init();

        // Устанавливаю URL, прокси и другие соответствующие опции для всех возможных proxy по указаной стране
        foreach ($proxys as $proxy) {
            foreach ($urls as $url) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,$url);
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
                //curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyauth);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Учитываю редиректы
                curl_setopt($ch, CURLOPT_NOBODY, 1); // Исключить тело ответа из вывода
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Возврат результата в качестве строки
                //curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, $time);
                curl_multi_add_handle($multi, $ch); //Добавляю обработчик для каждого комплекта данных параметров прокси
                $handle_proxys[$proxy] = $ch; //Создаю массив для последующей обработки - вывода ответа и удаления обработчика
            }
        }

        // Множественный обработчик
        do {
            $status = curl_multi_exec($multi, $active);
            if ($active) {
                curl_multi_select($multi);
            }
        } while ($active && $status == CURLM_OK);

        // Получаю нужные данные и закрываю дескрипторы
        foreach ($handle_proxys as $handle_proxy) {
            foreach ($urls as $url) {
                //echo curl_multi_getcontent($handle_proxy); // Получаю контент
                $http_code = curl_getinfo($handle_proxy, CURLINFO_HTTP_CODE); // Вывожу коды ответов с прокси
                curl_multi_remove_handle($multi, $handle_proxy); // Удаляю обработчик, после выполнения запроса выше
                $response[] = $http_code;
            }
        }

        // Закрываею набор cURL-дескрипторов
        curl_multi_close($multi);

        //print_r($response);
        if(substr($part_url, -1) == '/') :
            $part_url = substr($part_url, 0, -1);
        endif;

        if (in_array("200", $response)) {
            echo $part_url." — одним или несколькими прокси получен код 200. Полученные коды: ".implode(', ', $response);
        } else {
            echo $part_url." — код 200 не получен ни одним прокси выбранной страны. Полученные коды: ".implode(', ', $response);
        }
    }
}
?>