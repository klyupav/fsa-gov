<?php

namespace App\Donors;

use App\Http\Controllers\LoggerController;
use App\Models\ProxyList;
use function GuzzleHttp\Psr7\parse_query;
use ParseIt\_String;
use ParseIt\nokogiri;
use App\Donors\ParseIt\simpleParser;
use ParseIt\ParseItHelpers;

Class ArmnabAm_CertList extends simpleParser {

    public $data = [];
    public $reload = [];
    public $project = 'armnab.am';
    public $project_link = 'http://armnab.am/';
    public $source = 'http://armnab.am/CertlistRU?mode=5';
    public $cache = false;
    public $proxy = false;
    public $cookieFile = '';
    public $version_id = 1;
    public $donor = 'ArmnabAm_CertList';
    protected $token = '';
    protected $session = '';

    function __construct()
    {
        $this->cookieFile = __DIR__.'/cookie/'.class_basename(get_class($this)).'/'.class_basename(get_class($this)).'.txt';
    }

    public function getSources($opt = [])
    {
        $sources = [];

        if (isset($opt['url']))
        {
            $opt['origin'] = 'http://armnab.am';
            $opt['referer'] = 'http://armnab.am/CertlistRU?mode=5';
            $content = $this->loadUrl($opt['url'], $opt);
            if (empty($content))
            {
                return [];
            }
        }
        else
        {
            $opt['cookieFile'] = $this->cookieFile;

            $opt['headers'] = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
                'Accept-Encoding: gzip, deflate',
                'Accept-Language:en-US,en;q=0.9,ru;q=0.8',
                'Content-Type: application/x-www-form-urlencoded'
            ];

            $opt['host'] = 'armnab.am';
            $opt['page'] = 1;
            $content = $this->loadUrl($this->source, $opt);
        }

        $nokogiri = new nokogiri($content);

        $docViews = $nokogiri->get("#MainContent_ContentRU_gvDocs a[target=_blank]")->toArray();

        if (!isset($docViews[0]))
        {
            return [];
        }

        foreach ($docViews as $k => $item)
        {
            $href = $item['href'];
            $hash = md5($href);
            $sources[$hash]= [
                'hash' => $hash,
                'name' => '',
                'source' => $href,
                'donor_class_name' => $this->donor,
                'version' => 2,
                'param' => []
            ];
        }

        $opt['page'] = $opt['page'] + 1;
//        $opt['page'] = 40;

        $__VIEWSTATE = $nokogiri->get('#__VIEWSTATE')->toArray();
        $__VIEWSTATEGENERATOR = $nokogiri->get('#__VIEWSTATEGENERATOR')->toArray();
        $__EVENTVALIDATION = $nokogiri->get('#__EVENTVALIDATION')->toArray();

        if (!isset($__VIEWSTATE[0]['value']) || !isset($__VIEWSTATEGENERATOR[0]['value']) || !isset($__EVENTVALIDATION[0]['value']))
        {
            return $sources;
        }

        $post = [
            '__EVENTTARGET' => 'ctl00$ctl00$MainContent$ContentRU$gvDocs',
            '__EVENTARGUMENT' => "Page$".$opt['page'],
            '__VIEWSTATE' => $__VIEWSTATE[0]['value'],
            '__VIEWSTATEGENERATOR' => $__VIEWSTATEGENERATOR[0]['value'],
            '__EVENTVALIDATION' => $__EVENTVALIDATION[0]['value'],
            'ctl00$ctl00$MainContent$ContentRU$txtSearchRegNumber' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtSearchApplicant' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtSearchManufacturer' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtSearchProduct' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtSearchProductTechList' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtSearchProductTestReport' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtStartDate' => '',
            'ctl00$ctl00$MainContent$ContentRU$txtStartDateTo' => '',
        ];
        $opt['post'] = http_build_query($post);
        $opt['url'] = $this->source;

        $sourcesNextPage = $this->getSources($opt);

        foreach ($sourcesNextPage as $source)
        {
            $sources[$source['hash']] = $source;
        }

        return $sources;
    }

    public function getDataMMCert01RU($url, $source = [])
    {
        $data = false;

        $urlParsed = parse_url($url);
        $query = parse_query($urlParsed['query']);
        $number = $query['Number'];

        $source['cookieFile'] = $this->cookieFile;

        $source['headers'] = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'Accept-Language:en-US,en;q=0.9,ru;q=0.8',
        ];
        $source['host'] = 'armnab.am';

        $content = $this->loadUrl($this->source, $source);

        $source['post'] = "{'CertRegNumber':'{$number}', 'StatusFilter':'ALL'}";
        $source['ajax'] = true;
        $source['json'] = true;
        $source['origin'] = 'http://armnab.am';
        $source['referer'] = $url;
        $source['headers'] = [
            'Accept: application/json, text/javascript; q=0.01',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language:en-US,en;q=0.9,ru;q=0.8',
            'Content-Type: application/json; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest'
        ];

        $content = $this->loadUrl('http://armnab.am/CertificateRUService.asmx/GetMMCertificates', $source);

        if (!isset($content->d))
        {
            return [];
        }

        $items = json_decode($content->d);
        if (!isset($items[0]))
        {
            return [];
        }
        $item = $items[0];
//        print_r($item);die();
        $data[] = [
            'STATUS' => $item->Status,
            'REG_NUMBER' => $item->REG_NUMBER,
            'VALIDFROM_DATE' => $item->VALIDFROM_DATE,
            'EXPIRATION_DATE' => $item->EXPIRATION_DATE,
            'SERIAL_NUMBER' => $item->SERIAL_NUMBER,

            'APPLICANT_CORP_NAME' => $item->APPLICANT_CORP_NAME,
            'APPLICANT_CORP_LEADERNAME' => $item->APPLICANT_CORP_LEADERNAME,
            'APPLICANT_CORP_LEADERLASTNAME' => $item->APPLICANT_CORP_LEADERLASTNAME,
            'APPLICANT_CORP_REGNUMBER' => $item->APPLICANT_CORP_REGNUMBER,
            'APPLICANT_CORP_HVHH' => $item->APPLICANT_CORP_HVHH,
            'APPLICANT_CORP_PHONE' => $item->APPLICANT_CORP_PHONE,
            'APPLICANT_CORP_FAX' => $item->APPLICANT_CORP_FAX,
            'APPLICANT_CORP_EMAIL' => $item->APPLICANT_CORP_EMAIL,
            'APPLICANT_CORP_ADDRESS1' => $item->APPLICANT_CORP_ADDRESS1,
            'APPLICANT_CORP_ADDRESS2' => $item->APPLICANT_CORP_ADDRESS2,

            'APPLICANT_PERS_NAME' => $item->APPLICANT_PERS_NAME,
            'APPLICANT_PERS_LASTNAME' => $item->APPLICANT_PERS_LASTNAME,
            'APPLICANT_PERS_REGNUMBER' => $item->APPLICANT_PERS_REGNUMBER,
            'APPLICANT_PERS_HVHH' => $item->APPLICANT_PERS_HVHH,
            'APPLICANT_PERS_PHONE' => $item->APPLICANT_PERS_PHONE,
            'APPLICANT_PERS_FAX' => $item->APPLICANT_PERS_FAX,
            'APPLICANT_PERS_EMAIL' => $item->APPLICANT_PERS_EMAIL,
            'APPLICANT_PERS_ADDRESS1' => $item->APPLICANT_PERS_ADDRESS1,
            'APPLICANT_PERS_ADDRESS2' => $item->APPLICANT_PERS_ADDRESS2,

            'MANUFACTURER_EXT_NAME' => $item->MANUFACTURER_EXT_NAME,
            'MANUFACTURER_EXT_COUNTRY' => $item->MANUFACTURER_EXT_COUNTRY,
            'MANUFACTURER_EXT_ADDRESS' => $item->MANUFACTURER_EXT_ADDRESS,
            'MANUFACTURER_EXT_HVHH' => $item->MANUFACTURER_EXT_HVHH,
            'MANUFACTURER_EXT_PHONE' => $item->MANUFACTURER_EXT_PHONE,
            'MANUFACTURER_EXT_FAX' => $item->MANUFACTURER_EXT_FAX,
            'MANUFACTURER_EXT_EMAIL' => $item->MANUFACTURER_EXT_EMAIL,
            'PRODUCT_NAME' => $item->PRODUCT_NAME,
            'PRODUCT_SPECIFICATION' => $item->PRODUCT_SPECIFICATION,
            'PRODUCT_TK_NAME' => $item->PRODUCT_TK_NAME,
            'PRODUCT_TK_REKVISIT' => $item->PRODUCT_TK_REKVISIT,
            'PRODUCT_ST_NAME' => $item->PRODUCT_ST_NAME,
            'PRODUCT_ST_REKVISIT' => $item->PRODUCT_ST_REKVISIT,
            'PRODUCT_CS_NAME' => $item->PRODUCT_CS_NAME,
            'PRODUCT_CS_REKVISIT' => $item->PRODUCT_CS_REKVISIT,
            'PRODUCT_CERTOBJECT_TYPE' => $item->PRODUCT_CERTOBJECT_TYPE,
            'PRODUCT_BATCH' => $item->PRODUCT_BATCH,
            'PRODUCT_BATCH_DOCUMENTS' => $item->PRODUCT_BATCH_DOCUMENTS,
            'PRODUCT_MMATGAA' => $item->PRODUCT_MMATGAA,
            'PRODUCT_TECHLIST' => $item->PRODUCT_TECHLIST,
            'ProductTestReport' => $item->ProductTestReport,
            'ProductOtherDocuments' => $item->ProductOtherDocuments,
            'ProductExtraInfo' => $item->ProductExtraInfo,
            'Attachments' => $item->Attachments,
            'HGM_NAME' => $item->HGM_NAME,
            'AC_NUMBER' => $item->AC_NUMBER,
            'HGM_LEADER_NAME' => $item->HGM_LEADER_NAME,
            'HGM_LEADER_LASTNAME' => $item->HGM_LEADER_LASTNAME,
            'HGM_LEADER_FATHERNAME' => $item->HGM_LEADER_FATHERNAME,
            'HGM_PHONE' => $item->HGM_PHONE,
            'HGM_FAX' => $item->HGM_FAX,
            'HGM_EMAIL' => $item->HGM_EMAIL,
            'HGM_ADDRESS1' => $item->HGM_ADDRESS1,
            'HGM_ADDRESS2' => $item->HGM_ADDRESS2,
            'HGM_EXPERT_NAME' => $item->HGM_EXPERT_NAME,
            'HGM_EXPERT_LASTNAME' => $item->HGM_EXPERT_LASTNAME,
            'HGM_EXPERT_FATHERNAME' => $item->HGM_EXPERT_FATHERNAME,
        ];
//        print_r($data);die();

        return $data;
    }

    public function getDataR_TR_TS_01_001($url, $source = [])
    {
        $data = false;

//        $url = "http://register.armnab.am/R_TR_TS_01_001/docview/23238";

        $source['cookieFile'] = $this->cookieFile;

        $source['headers'] = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'Accept-Language:en-US,en;q=0.9,ru;q=0.8',
        ];
        $source['host'] = 'register.armnab.am';

        $content = $this->loadUrl($url, $source);

        $nokogiri = new nokogiri($content);

        $form_group = $nokogiri->get('.box-body .form-group')->toArray();

        $Doc_Type = @$form_group[0]['label'][1]['__ref']->nodeValue;
        $REG_NUMBER = @$form_group[1]['label'][1]['__ref']->nodeValue;
        $VALIDFROM_DATE = @$form_group[2]['label'][1]['__ref']->nodeValue;
        $EXPIRATION_DATE = @$form_group[3]['label'][1]['__ref']->nodeValue;
        $SERIAL_NUMBER = @$form_group[4]['label'][1]['__ref']->nodeValue;
        $ORG_PO_OCENKE_SOOTVET = @$form_group[5]['label'][1]['__ref']->nodeValue;
        $SCHEME_SERTIFIC = @$form_group[6]['label'][1]['__ref']->nodeValue;
        $TYPE_OBJ_TR = @$form_group[7]['label'][1]['__ref']->nodeValue;

        $STATUS = @$form_group[8]['label'][1]['__ref']->nodeValue;
        $STATUS_DATE_BEGIN = @$form_group[9]['label'][1]['__ref']->nodeValue;

        $TK_REKVISIT = @$form_group[10]['label'][1]['__ref']->nodeValue;

        $APPLICANT_PERS_NAME = @$form_group[11]['label'][1]['__ref']->nodeValue;
        $APPLICANT_PERS_OPF = @$form_group[12]['label'][1]['__ref']->nodeValue;
        $APPLICANT_PERS_COUNTRY = @$form_group[13]['label'][1]['__ref']->nodeValue;
        $APPLICANT_PERS_REGNUMBER = @$form_group[14]['label'][1]['__ref']->nodeValue;
        $APPLICANT_PERS_HVHH = @$form_group[15]['label'][1]['__ref']->nodeValue;

        $ApplicantAddressList = $nokogiri->get('#ApplicantAddressList tbody tr')->toArray();
        $APPLICANT_PERS_ADDRESS = [];
        foreach ( $ApplicantAddressList as $row)
        {
            $APPLICANT_PERS_ADDRESS[] = [
                'вид адреса' => @$row['td'][0]['__ref']->nodeValue,
                'Регион' => @$row['td'][1]['__ref']->nodeValue,
                'Район' => @$row['td'][2]['__ref']->nodeValue,
                'Улица' => @$row['td'][3]['__ref']->nodeValue,
                'Номер дома' => @$row['td'][4]['__ref']->nodeValue,
                'Почтовый индекс' => @$row['td'][5]['__ref']->nodeValue,
            ];
        }

        $ApplicantContactList = $nokogiri->get('#ApplicantContactList tbody tr')->toArray();
        $APPLICANT_PERS_CONTACTS = [];
        foreach ( $ApplicantContactList as $row)
        {
            $APPLICANT_PERS_CONTACTS[] = [
                'Вид контакта' => @$row['td'][0]['__ref']->nodeValue,
                'Контакт' => @$row['td'][0]['__ref']->nodeValue,
            ];
        }

        $divApplicant = $nokogiri->get('#divApplicant .box-body')->toArray();
        $APPLICANT_PERS_FILIALS = [];
        foreach ( $divApplicant[0]['table'][0]['tbody'][0]['tr'] as $row)
        {
            if (!isset($row['td']) || count($row['td']) != 6)
            {
                continue;
            }
            $APPLICANT_PERS_FILIALS[] = [
                'Страна' => trim(@$row['td'][0]['__ref']->nodeValue),
                'УНН' => @$row['td'][1]['__ref']->nodeValue,
                'Наименование хозяйствующего субъекта' => @$row['td'][2]['__ref']->nodeValue,
                'Наименование организационно-правовой формы' => @$row['td'][3]['__ref']->nodeValue,
                'Номер государственной регистрации' => @$row['td'][4]['__ref']->nodeValue,
            ];
        }

        $product_tables_tr = $nokogiri->get('#divProduct .box-body tbody tr')->toArray();
        $PRODUCT_LIST = [];
        foreach ($product_tables_tr as $row)
        {
            if (!isset($row['td']) || isset($row['id']) || count($row['td']) != 6)
            {
                continue;
            }
            $PRODUCT_LIST[] = [
                'Наименование продукции' => @$row['td'][0]['__ref']->nodeValue,
                'Описание продукта' => @$row['td'][1]['__ref']->nodeValue,
                'Код товара по ТН ВЭД ЕАЭС' => @$row['td'][2]['__ref']->nodeValue,
                'Размер партии' => @$row['td'][3]['__ref']->nodeValue,
                'Дополнительная информация' => @$row['td'][4]['__ref']->nodeValue,
            ];
        }

        $ProductInstanceList = $nokogiri->get('#divProduct #ProductInstanceList table.table-striped')->toArray();
        $PRODUCT_BATCH = [];
        foreach ($ProductInstanceList as $table)
        {
            if (!isset($table['tr']) || count($table['tr']) != 7)
            {
                continue;
            }
            $PRODUCT_BATCH[] = [
                'Количество продукции и единица измерения' => @$table['tr'][0]['td'][0]['__ref']->nodeValue,
                'заводской номер единичного изделия' => @$table['tr'][1]['td'][0]['__ref']->nodeValue,
                'Наименование группы одинаковых единиц продукции' => @$table['tr'][2]['td'][0]['__ref']->nodeValue,
                'Дополнительные сведения о продукции' => @$table['tr'][3]['td'][0]['__ref']->nodeValue,
                'Дата изготовления' => @$table['tr'][4]['td'][0]['__ref']->nodeValue,
                'Дата истечения срока годности' => @$table['tr'][5]['td'][0]['__ref']->nodeValue,
                'Код товара по ТН ВЭД ЕАЭС' => @$table['tr'][6]['td'][0]['__ref']->nodeValue,
            ];
        }

        $DocInformationList = $nokogiri->get('#divProduct #DocInformationList tbody tr')->toArray();
        $PRODUCT_TECHLIST = [];
        foreach ($DocInformationList as $row)
        {
            if (!isset($row['td']) || isset($row['id']) || count($row['td']) != 3)
            {
                continue;
            }
            $PRODUCT_TECHLIST[] = [
                'наименование' => @$row['td'][0]['__ref']->nodeValue,
                'реквизиты' => @$row['td'][1]['__ref']->nodeValue,
            ];
        }

        $divManufacturer_tr = $nokogiri->get('#divManufacturer .box-body tbody tr')->toArray();
        $MANUFACTURER_INFO = [];
        foreach ($divManufacturer_tr as $row)
        {
            if (!isset($row['td']) || isset($row['id']) || count($row['td']) != 5)
            {
                continue;
            }
            $MANUFACTURER_INFO = [
                'Изготовитель' => [
                    'Страна' => trim(@$row['td'][0]['__ref']->nodeValue),
                    'Наименование хозяйствующего субъекта' => @$row['td'][1]['__ref']->nodeValue,
                    'Наименование организационно-правовой формы' => @$row['td'][2]['__ref']->nodeValue,
                    'номер государственной регистрации' => @$row['td'][3]['__ref']->nodeValue,
                ]
            ];
        }

        foreach ($divManufacturer_tr as $row)
        {
            if (isset($row['id']) && preg_match('%ManufacturerAddressList%uis', $row['id']))
            {
                foreach ($row['td'][1]['div'][0]['table'][0]['tbody'][0]['tr'] as $tr)
                {
                    $MANUFACTURER_INFO['Адрес(а)'][] = [
                        'вид адреса' => @$tr['td'][0]['__ref']->nodeValue,
                        'Регион' => @$tr['td'][1]['__ref']->nodeValue,
                        'Район' => @$tr['td'][2]['__ref']->nodeValue,
                        'Улица' => @$tr['td'][3]['__ref']->nodeValue,
                        'Номер дома' => @$tr['td'][4]['__ref']->nodeValue,
                        'Почтовый индекс' => @$tr['td'][5]['__ref']->nodeValue,
                    ];
                }
            }
        }

        $ManufacturerBranchList = $nokogiri->get('#divManufacturer #ManufacturerBranchList tbody')->toArray();

        if (isset($ManufacturerBranchList[0]['tr']))
        {
            $i = 0;
            foreach ($ManufacturerBranchList[0]['tr'] as $row)
            {
                if (!isset($row['id']) && count($row['td']) == 6)
                {
                    $i++;
                    $MANUFACTURER_INFO['Филиал(ы)'][$i] = [
                        'Страна' => trim(@$row['td'][0]['__ref']->nodeValue),
                        'Наименование хозяйствующего субъекта' => @$row['td'][1]['__ref']->nodeValue,
                        'Наименование организационно-правовой формы' => @$row['td'][2]['__ref']->nodeValue,
                        'номер государственной регистрации' => @$row['td'][3]['__ref']->nodeValue,
                        'УНН' => @$row['td'][4]['__ref']->nodeValue,
                    ];
                }
            }

            $i = 0;
            foreach ($ManufacturerBranchList[0]['tr'] as $row)
            {
                if (isset($row['id']) && preg_match('%ManufacturerBranchAddressList%uis', $row['id']))
                {
                    $i++;
                    foreach ($row['td'][1]['div'][0]['table'][0]['tbody'][0]['tr'] as $tr_adress)
                    {
                        $MANUFACTURER_INFO['Филиал(ы)'][$i]['Адрес(а) филиала'][] = [
                            'вид адреса' => @$tr_adress['td'][0]['__ref']->nodeValue,
                            'Регион' => @$tr_adress['td'][1]['__ref']->nodeValue,
                            'Район' => @$tr_adress['td'][2]['__ref']->nodeValue,
                            'Улица' => @$tr_adress['td'][3]['__ref']->nodeValue,
                            'Номер дома' => @$tr_adress['td'][4]['__ref']->nodeValue,
                            'Почтовый индекс' => @$tr_adress['td'][5]['__ref']->nodeValue,
                        ];
                    }
                }
            }
        }

        $blocks = $nokogiri->get('.content .row .col-md-12')->toArray()[0]['div'];
        $PRODUCT_BATCH_DOCUMENTS = [];
        foreach ($blocks[4]['div'][1]['div'][0]['table'][0]['tbody'][0]['tr'] as $row)
        {
            if (!isset($row['id']) && count($row['td']) == 3)
            {
                $PRODUCT_BATCH_DOCUMENTS[] = [
                    'наименование' => trim(@$row['td'][0]['__ref']->nodeValue),
                    'реквизиты' => @$row['td'][1]['__ref']->nodeValue,
                ];
            }
        }

        $divComplianceDocDetail_tr = $nokogiri->get('#divComplianceDocDetail .box-body tbody tr')->toArray();
        $ProductExtraInfo = [];
        foreach ($divComplianceDocDetail_tr as $row)
        {
            if (!isset($row['td']) || count($row['td']) != 6)
            {
                continue;
            }
            $ProductExtraInfo[] = [
                'Наименование документа' => trim(@$row['td'][0]['__ref']->nodeValue),
                'Дата документа' => @$row['td'][1]['__ref']->nodeValue,
                'Номер документа' => @$row['td'][2]['__ref']->nodeValue,
                'Наименование хозяйствующего субъекта, выдавшего документ' => @$row['td'][3]['__ref']->nodeValue,
                'Номер документа, подтверждающего аккредитацию хозяйствующего субъекта' => @$row['td'][4]['__ref']->nodeValue,
            ];
        }

        $blocks = $nokogiri->get('.content .row .col-md-12')->toArray()[0]['div'];
        $EXPERT_INFO = [];
        foreach ($blocks[6]['div'][1]['table'][0]['tbody'][0]['tr'] as $row)
        {
            if (!isset($row['id']) && count($row['td']) == 4)
            {
                $EXPERT_INFO[] = [
                    'Имя' => trim(@$row['td'][0]['__ref']->nodeValue),
                    'Фамилия' => @$row['td'][1]['__ref']->nodeValue,
                    'Отчество' => @$row['td'][2]['__ref']->nodeValue,
                ];
            }
        }

        $divDocAnnexDetails = $nokogiri->get('#divDocAnnexDetails .box-body tbody tr')->toArray();
        $Attachments = [];
        foreach ($divDocAnnexDetails as $row)
        {
            if (!isset($row['td']) || count($row['td']) != 4)
            {
                continue;
            }
            $Attachments[] = [
                'Порядковый номер' => trim(@$row['td'][0]['__ref']->nodeValue),
                'Номер бланка' => @$row['td'][1]['__ref']->nodeValue,
                'Количество листов' => @$row['td'][2]['__ref']->nodeValue,
            ];
        }

        $data[] = [
            'Doc_Type' => @$Doc_Type,
            'REG_NUMBER' => @$REG_NUMBER,
            'VALIDFROM_DATE' => @$VALIDFROM_DATE,
            'EXPIRATION_DATE' => @$EXPIRATION_DATE,
            'SERIAL_NUMBER' => @$SERIAL_NUMBER,
            'ORG_PO_OCENKE_SOOTVET' => @$ORG_PO_OCENKE_SOOTVET,
            'SCHEME_SERTIFIC' => @$SCHEME_SERTIFIC,
            'TYPE_OBJ_TR' => @$TYPE_OBJ_TR,
            'STATUS' => @$STATUS,
            'STATUS_DATE_BEGIN' => @$STATUS_DATE_BEGIN,
            'TK_REKVISIT' => @$TK_REKVISIT,
            'APPLICANT_PERS_NAME' => @$APPLICANT_PERS_NAME,
            'APPLICANT_PERS_OPF' => @$APPLICANT_PERS_OPF,
            'APPLICANT_PERS_COUNTRY' => @$APPLICANT_PERS_COUNTRY,
            'APPLICANT_PERS_REGNUMBER' => @$APPLICANT_PERS_REGNUMBER,
            'APPLICANT_PERS_HVHH' => @$APPLICANT_PERS_HVHH,
            'APPLICANT_PERS_ADDRESS' => serialize($APPLICANT_PERS_ADDRESS),
            'APPLICANT_PERS_CONTACTS' => serialize($APPLICANT_PERS_CONTACTS),
            'APPLICANT_PERS_FILIALS' => serialize($APPLICANT_PERS_FILIALS),
            'PRODUCT_LIST' => serialize($PRODUCT_LIST),
            'PRODUCT_BATCH' => serialize($PRODUCT_BATCH),
            'PRODUCT_TECHLIST' => serialize($PRODUCT_TECHLIST),
            'MANUFACTURER_INFO' => serialize($MANUFACTURER_INFO),
            'PRODUCT_BATCH_DOCUMENTS' => serialize($PRODUCT_BATCH_DOCUMENTS),
            'ProductExtraInfo' => serialize($ProductExtraInfo),
            'EXPERT_INFO' => serialize($EXPERT_INFO),
            'Attachments' => serialize($Attachments),
        ];
//        print_r($data);die();

        return $data;
    }

    public function getDocTypeByLink($link)
    {
        if (preg_match('%MMCert01RU%uis', $link))
        {
            return 'MMCert01RU';
        }
        elseif (preg_match('%R_TR_TS_01_001%uis', $link))
        {
            return 'R_TR_TS_01_001';
        }
        else
        {
            return 'dont now';
        }
    }
}