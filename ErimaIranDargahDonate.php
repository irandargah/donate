<?php
/*
Plugin Name: Irandargah Donate - حمایت مالی
Description: افزونه حمایت مالی از وبسایت ها -- برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید  [ErimaIrandargahDonate]
Version: 1.0
 */

defined('ABSPATH') or die('Access denied!');
define('ErimaIrandargahDonateDIR', plugin_dir_path(__FILE__));
define('LIBDIR', ErimaIrandargahDonateDIR . '/lib');
define('TABLE_DONATE', 'erima_donate');

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if (is_admin()) {
    add_action('admin_menu', 'EIRD_AdminMenuItem');
    function EIRD_AdminMenuItem()
    {
        add_menu_page('تنظیمات افزونه حمایت مالی - ایران درگاه', 'حمایت مالی', 'administrator', 'EIRD_MenuItem', 'EIRD_MainPageHTML', /*plugins_url( 'myplugin/images/icon.png' )*/'', 6);
        add_submenu_page('EIRD_MenuItem', 'نمایش حامیان مالی', 'نمایش حامیان مالی', 'administrator', 'EIRD_Hamian', 'EIRD_HamianHTML');
    }
}

function EIRD_MainPageHTML()
{
    include 'EIRD_AdminPage.php';
}

function EIRD_HamianHTML()
{
    include 'EIRD_Hamian.php';
}

add_action('init', 'ErimaIrandargahDonateShortcode');
function ErimaIrandargahDonateShortcode()
{
    add_shortcode('ErimaIrandargahDonate', 'ErimaIrandargahDonateForm');
}

function ErimaIrandargahDonateForm()
{
    $out = '';
    $error = '';
    $message = '';

    $MerchantID = get_option('EIRD_MerchantID');
    $EIRD_IsOK = get_option('EIRD_IsOK');
    $EIRD_IsError = get_option('EIRD_IsError');
    $EIRD_Unit = get_option('EIRD_Unit');

    $Amount = '';
    $Description = '';
    $Name = '';
    $Mobile = '';
    $Email = '';

    //////////////////////////////////////////////////////////
    //            REQUEST
    if (isset($_POST['submit']) && $_POST['submit'] == 'پرداخت') {

        if ($MerchantID == '') {
            $error = 'کد دروازه پرداخت وارد نشده است' . "<br>\r\n";
        }

        $Amount = filter_input(INPUT_POST, 'EIRD_Amount', FILTER_SANITIZE_SPECIAL_CHARS);

        if (is_numeric($Amount) != false) {
            //Amount will be based on Toman  - Required
            if ($EIRD_Unit == 'ریال') {
                $SendAmount = $Amount;
            } else {
                $SendAmount = $Amount * 10;
            }

        } else {
            $error .= 'مبلغ به درستی وارد نشده است' . "<br>\r\n";
        }

        $Description = filter_input(INPUT_POST, 'EIRD_Description', FILTER_SANITIZE_SPECIAL_CHARS); // Required
        $Name = filter_input(INPUT_POST, 'EIRD_Name', FILTER_SANITIZE_SPECIAL_CHARS); // Required
        $Mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_SPECIAL_CHARS); // Optional
        $Email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS); // Optional

        $SendDescription = $Name . ' | ' . $Mobile . ' | ' . $Email . ' | ' . $Description;

        if ($error == '') // اگر خطایی نباشد
        {
            $CallbackURL = EIRD_GetCallBackURL(); // Required

            // URL also Can be https://ir.irandargah.com/pg/services/WebGate/wsdl

            $client = new SoapClient('https://www.dargaah.com/wsdl', ['encoding' => 'UTF-8']);

            $result = $client->IRDPayment(
                [
                    'merchantID' => $MerchantID,
                    'amount' => $SendAmount,
                    'description' => $SendDescription,
                    'mobile' => $Mobile,
                    'callbackURL' => $CallbackURL,
                ]
            );

            //Redirect to URL You can do it also by creating a form
            if ($result->status == 200) {
                // WruteToDB

                EIRD_AddDonate(array(
                    'Authority' => $result->authority,
                    'Name' => $Name,
                    'AmountRial' => $SendAmount,
                    'Mobile' => $Mobile,
                    'Email' => $Email,
                    'InputDate' => current_time('mysql'),
                    'Description' => $Description,
                    'Status' => 'SEND',
                ), array(
                    '%s',
                    '%s',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ));

                $Location = 'https://dargaah.com/ird/startpay/' . $result->authority;

                return "<script>document.location = '${Location}'</script><center>در صورتی که به صورت خودکار به درگاه بانک منتقل نشدید <a href='${Location}'>اینجا</a> را کلیک کنید.</center>";
            } else {
                $error .= EIRD_GetResaultStatusString($result->status) . "<br>\r\n";
            }
        }
    }
    //// END REQUEST

    ////////////////////////////////////////////////////
    ///             RESPONSE
    if (isset($_POST['authority'])) {

        $Authority = filter_input(INPUT_POST, 'authority', FILTER_SANITIZE_SPECIAL_CHARS);

        if ($_POST['code'] == 100) {

            $Record = EIRD_GetDonate($Authority);
            if ($Record === false) {
                $error .= 'چنین تراکنشی در سایت ثبت نشده است' . "<br>\r\n";
            } else {
                $client = new SoapClient('https://www.dargaah.com/wsdl', ['encoding' => 'UTF-8']);

                $result = $client->IRDVerification(
                    [
                        'merchantID' => $MerchantID,
                        'authority' => $Record['Authority'],
                        'amount' => $Record['AmountRial'],
                    ]
                );

                if ($result->status == 100) {
                    EIRD_ChangeStatus($Authority, 'OK');
                    $message .= get_option('EIRD_IsOk') . "<br>\r\n";
                    $message .= 'کد پیگیری تراکنش:' . $result->refId . "<br>\r\n";

                    $EIRD_TotalAmount = get_option("EIRD_TotalAmount");
                    update_option("EIRD_TotalAmount", $EIRD_TotalAmount + $Record['AmountRial']);
                } else {
                    EIRD_ChangeStatus($Authority, 'ERROR');
                    $error .= get_option('EIRD_IsError') . "<br>\r\n";
                    $error .= EIRD_GetResaultStatusString($result->status) . "<br>\r\n";
                }
            }
        } else {
            $error .= EIRD_GetResaultStatusString($_POST['code']);
            EIRD_ChangeStatus($Authority, 'CANCEL');
        }
    }
    ///     END RESPONSE

    $style = '';

    if (get_option('EIRD_UseCustomStyle') == 'true') {
        $style = get_option('EIRD_CustomStyle');
    } else {
        $style = '#EIRD_MainForm {  width: 400px;  height: auto;  margin: 0 auto;  direction: rtl; }  #EIRD_Form {  width: 96%;  height: auto;  float: right;  padding: 10px 2%; }  #EIRD_Message,#EIRD_Error {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%;  border-right: 2px solid #006704;  background-color: #e7ffc5;  color: #00581f; }  #EIRD_Error {  border-right: 2px solid #790000;  background-color: #ffc9c5;  color: #580a00; }  .EIRD_FormItem {  width: 90%;  margin-top: 10px;  margin-right: 2%;  float: right;  padding: 5px 2%; }    .EIRD_FormLabel {  width: 35%;  float: right;  padding: 3px 0; }  .EIRD_ItemInput {  width: 64%;  float: left; }  .EIRD_ItemInput input {  width: 90%;  float: right;  border-radius: 3px;  box-shadow: 0 0 2px #00c4ff;  border: 0px solid #c0fff0;  font-family: inherit;  font-size: inherit;  padding: 3px 5px; }  .EIRD_ItemInput input:focus {  box-shadow: 0 0 4px #0099d1; }  .EIRD_ItemInput input.error {  box-shadow: 0 0 4px #ef0d1e; }  input.EIRD_Submit {  background: none repeat scroll 0 0 #2ea2cc;  border-color: #0074a2;  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);  color: #fff;  text-decoration: none;  border-radius: 3px;  border-style: solid;  border-width: 1px;  box-sizing: border-box;  cursor: pointer;  display: inline-block;  font-size: 13px;  line-height: 26px;  margin: 0;  padding: 0 10px 1px;  margin: 10px auto;  width: 50%;  font: inherit;  float: right;  margin-right: 24%; }';
    }

    $out = '
  <style>
    ' . $style . '
  </style>
      <div style="clear:both;width:100%;float:right;">
	        <div id="EIRD_MainForm">
          <div id="EIRD_Form">';

    if ($message != '') {
        $out .= "<div id=\"EIRD_Message\">
    ${message}
            </div>";
    }

    if ($error != '') {
        $out .= "<div id=\"EIRD_Error\">
    ${error}
            </div>";
    }

    $out .= '<form method="post">
              <div class="EIRD_FormItem">
                <label class="EIRD_FormLabel">مبلغ :</label>
                <div class="EIRD_ItemInput">
                  <input style="width:60%" type="text" name="EIRD_Amount" value="' . $Amount . '" />
                  <span style="margin-right:10px;">' . $EIRD_Unit . '</span>
                </div>
              </div>

              <div class="EIRD_FormItem">
                <label class="EIRD_FormLabel">نام و نام خانوادگی :</label>
                <div class="EIRD_ItemInput"><input type="text" name="EIRD_Name" value="' . $Name . '" /></div>
              </div>

              <div class="EIRD_FormItem">
                <label class="EIRD_FormLabel">تلفن همراه :</label>
                <div class="EIRD_ItemInput"><input type="text" name="mobile" value="' . $Mobile . '" /></div>
              </div>

              <div class="EIRD_FormItem">
                <label class="EIRD_FormLabel">ایمیل :</label>
                <div class="EIRD_ItemInput"><input type="text" name="email" style="direction:ltr;text-align:left;" value="' . $Email . '" /></div>
              </div>

              <div class="EIRD_FormItem">
                <label class="EIRD_FormLabel">توضیحات :</label>
                <div class="EIRD_ItemInput"><input type="text" name="EIRD_Description" value="' . $Description . '" /></div>
              </div>

              <div class="EIRD_FormItem">
                <input type="submit" name="submit" value="پرداخت" class="EIRD_Submit" />
              </div>

            </form>
          </div>
        </div>
      </div>
	';

    return $out;
}

/////////////////////////////////////////////////
// تنظیمات اولیه در هنگام اجرا شدن افزونه.
register_activation_hook(__FILE__, 'EriamIrandargahDonate_install');
function EriamIrandargahDonate_install()
{
    EIRD_CreateDatabaseTables();
}
function EIRD_CreateDatabaseTables()
{
    global $wpdb;
    $erimaDonateTable = $wpdb->prefix . TABLE_DONATE;
    // Creat table
    $nazrezohoor = "CREATE TABLE IF NOT EXISTS `$erimaDonateTable` (
					  `DonateID` int(11) NOT NULL AUTO_INCREMENT,
					  `Authority` varchar(50) NOT NULL,
					  `Name` varchar(50) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
					  `AmountRial` int(11) NOT NULL,
					  `Mobile` varchar(11) ,
					  `Email` varchar(50),
					  `InputDate` varchar(20),
					  `Description` varchar(100) CHARACTER SET utf8 COLLATE utf8_persian_ci,
					  `Status` varchar(5),
					  PRIMARY KEY (`DonateID`),
					  KEY `DonateID` (`DonateID`)
					) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
    dbDelta($nazrezohoor);
    // Other Options
    add_option("EIRD_TotalAmount", 0, '', 'yes');
    add_option("EIRD_TotalPayment", 0, '', 'yes');
    add_option("EIRD_IsOK", 'با تشکر پرداخت شما به درستی انجام شد.', '', 'yes');
    add_option("EIRD_IsError", 'متاسفانه پرداخت انجام نشد.', '', 'yes');

    $style = '#EIRD_MainForm {
  width: 400px;
  height: auto;
  margin: 0 auto;
  direction: rtl;
}

#EIRD_Form {
  width: 96%;
  height: auto;
  float: right;
  padding: 10px 2%;
}

#EIRD_Message,#EIRD_Error {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
  border-right: 2px solid #006704;
  background-color: #e7ffc5;
  color: #00581f;
}

#EIRD_Error {
  border-right: 2px solid #790000;
  background-color: #ffc9c5;
  color: #580a00;
}

.EIRD_FormItem {
  width: 90%;
  margin-top: 10px;
  margin-right: 2%;
  float: right;
  padding: 5px 2%;
}

.EIRD_FormLabel {
  width: 35%;
  float: right;
  padding: 3px 0;
}

.EIRD_ItemInput {
  width: 64%;
  float: left;
}

.EIRD_ItemInput input {
  width: 90%;
  float: right;
  border-radius: 3px;
  box-shadow: 0 0 2px #00c4ff;
  border: 0px solid #c0fff0;
  font-family: inherit;
  font-size: inherit;
  padding: 3px 5px;
}

.EIRD_ItemInput input:focus {
  box-shadow: 0 0 4px #0099d1;
}

.EIRD_ItemInput input.error {
  box-shadow: 0 0 4px #ef0d1e;
}

input.EIRD_Submit {
  background: none repeat scroll 0 0 #2ea2cc;
  border-color: #0074a2;
  box-shadow: 0 1px 0 rgba(120, 200, 230, 0.5) inset, 0 1px 0 rgba(0, 0, 0, 0.15);
  color: #fff;
  text-decoration: none;
  border-radius: 3px;
  border-style: solid;
  border-width: 1px;
  box-sizing: border-box;
  cursor: pointer;
  display: inline-block;
  font-size: 13px;
  line-height: 26px;
  margin: 0;
  padding: 0 10px 1px;
  margin: 10px auto;
  width: 50%;
  font: inherit;
  float: right;
  margin-right: 24%;
}';
    add_option("EIRD_CustomStyle", $style, '', 'yes');
    add_option("EIRD_UseCustomStyle", 'false', '', 'yes');
}

function EIRD_GetDonate($Authority)
{
    global $wpdb;
    $Authority = strip_tags(esc_sql($Authority));

    if ($Authority == '') {
        return false;
    }

    $erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

    $res = $wpdb->get_results("SELECT * FROM ${erimaDonateTable} WHERE Authority = '${Authority}' LIMIT 1", ARRAY_A);

    if (count($res) == 0) {
        return false;
    }

    return $res[0];
}

function EIRD_AddDonate($Data, $Format)
{
    global $wpdb;

    if (!is_array($Data)) {
        return false;
    }

    $erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

    $res = $wpdb->insert($erimaDonateTable, $Data, $Format);

    if ($res == 1) {
        $totalPay = get_option('EIRD_TotalPayment');
        $totalPay += 1;
        update_option('EIRD_TotalPayment', $totalPay);
    }

    return $res;
}

function EIRD_ChangeStatus($Authority, $Status)
{
    global $wpdb;
    $Authority = strip_tags(esc_sql($Authority));
    $Status = strip_tags(esc_sql($Status));

    if ($Authority == '' || $Status == '') {
        return false;
    }

    $erimaDonateTable = $wpdb->prefix . TABLE_DONATE;

    $res = $wpdb->query("UPDATE ${erimaDonateTable} SET `Status` = '${Status}' WHERE `Authority` = '${Authority}'");

    return $res;
}

function EIRD_GetResaultStatusString($StatusNumber)
{
    switch ($StatusNumber) {
        case 200:
            return 'اتصال به درگاه بانک با موفقیت انجام شده است.';
        case 201:
            return 'درحال پرداخت در درگاه بانک.';
        case 100:
            return 'تراکنش با موفقیت انجام شده است.';
        case 101:
            return 'تراکنش قبلا verify شده است.';
        case 404:
            return 'تراکنش یافت نشد.';
        case 403:
            return 'کد مرچنت صحیح نمی باشد.';
        case -1:
            return 'کاربر از انجام تراکنش منصرف شده است.';
        case -10:
            return 'مبلغ تراکنش کمتر از 10,000 ریال است.';
        case -11:
            return 'مبلغ تراکنش با مبلغ پرداختی یکسان نیست. مبلغ برگشت خورد.';
        case -12:
            return 'شماره کارتی که با آن تراکنش انجام شده است با شماره کارت ارسالی مغایرت دارد. مبلغ برگشت خورد.';
        case -13:
            return 'تراکنش تکراری است.';
        case -20:
            return 'شناسه تراکنش یافت نشد.';
        case -21:
            return 'مدت زمان مجاز جهت ارسال به بانک گذشته است.';
        case -22:
            return 'تراکنش برای بانک ارسال شده است.';
        case -23:
            return 'خطا در اتصال به درگاه بانک.';
        case -30:
            return 'اشکالی در فرایند پرداخت ایجاد شده است.مبلغ برگشت خورد.';
        case -31:
        default:
            return 'خطای ناشناخته';
    }

    return '';
}

function EIRD_GetCallBackURL()
{
    $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";

    $ServerName = htmlspecialchars($_SERVER["SERVER_NAME"], ENT_QUOTES, "utf-8");
    $ServerPort = htmlspecialchars($_SERVER["SERVER_PORT"], ENT_QUOTES, "utf-8");
    $ServerRequestUri = htmlspecialchars($_SERVER["REQUEST_URI"], ENT_QUOTES, "utf-8");

    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $ServerName . ":" . $ServerPort . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $ServerName . $ServerRequestUri;
    }
    return $pageURL;
}
