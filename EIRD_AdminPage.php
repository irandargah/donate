<?php

defined('ABSPATH') or die('Access denied!');

if ($_POST) {

    if (isset($_POST['EIRD_MerchantID'])) {
        update_option('EIRD_MerchantID', $_POST['EIRD_MerchantID']);
    }

    if (isset($_POST['EIRD_IsOK'])) {
        update_option('EIRD_IsOK', $_POST['EIRD_IsOK']);
    }

    if (isset($_POST['EIRD_IsError'])) {
        update_option('EIRD_IsError', $_POST['EIRD_IsError']);
    }

    if (isset($_POST['EIRD_Unit'])) {
        update_option('EIRD_Unit', $_POST['EIRD_Unit']);
    }

    if (isset($_POST['EIRD_UseCustomStyle'])) {
        update_option('EIRD_UseCustomStyle', 'true');

        if (isset($_POST['EIRD_CustomStyle'])) {
            update_option('EIRD_CustomStyle', strip_tags($_POST['EIRD_CustomStyle']));
        }

    } else {
        update_option('EIRD_UseCustomStyle', 'false');
    }

    echo '<div class="updated" id="message"><p><strong>تنظیمات ذخیره شد</strong>.</p></div>';

}
//XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
?>
<h2 id="add-new-user">تنظیمات افزونه حمایت مالی - ایران درگاه</h2>
<h2 id="add-new-user">جمع تمام پرداخت ها : <?php echo get_option("EIRD_TotalAmount"); ?>  تومان</h2>
<form method="post">
  <table class="form-table">
    <tbody>
      <tr class="user-first-name-wrap">
        <th><label for="EIRD_MerchantID">کد دروازه پرداخت</label></th>
        <td>
          <input type="text" class="regular-text" value="<?php echo get_option('EIRD_MerchantID'); ?>" id="EIRD_MerchantID" name="EIRD_MerchantID">
          <p class="description indicator-hint">XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX</p>
        </td>
      </tr>
      <tr>
        <th><label for="EIRD_IsOK">پرداخت صحیح</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option('EIRD_IsOK'); ?>" id="EIRD_IsOK" name="EIRD_IsOK"></td>
      </tr>
      <tr>
        <th><label for="EIRD_IsError">خطا در پرداخت</label></th>
        <td><input type="text" class="regular-text" value="<?php echo get_option('EIRD_IsError'); ?>" id="EIRD_IsError" name="EIRD_IsError"></td>
      </tr>

      <tr class="user-display-name-wrap">
        <th><label for="EIRD_Unit">واحد پول</label></th>
        <td>
          <?php $EIRD_Unit = get_option('EIRD_Unit');?>
          <select id="EIRD_Unit" name="EIRD_Unit">
            <option <?php if ($EIRD_Unit == 'تومان') {
    echo 'selected="selected"';
}
?>>تومان</option>
            <option <?php if ($EIRD_Unit == 'ریال') {
    echo 'selected="selected"';
}
?>>ریال</option>
          </select>
        </td>
      </tr>

      <tr class="user-display-name-wrap">
        <th>استفاده از استایل سفارشی</th>
        <td>
          <?php $EIRD_UseCustomStyle = get_option('EIRD_UseCustomStyle') == 'true' ? 'checked="checked"' : '';?>
          <input type="checkbox" name="EIRD_UseCustomStyle" id="EIRD_UseCustomStyle" value="true" <?php echo $EIRD_UseCustomStyle ?> /><label for="EIRD_UseCustomStyle">استفاده از استایل سفارشی برای فرم</label><br>
        </td>
      </tr>


      <tr class="user-display-name-wrap" id="EIRD_CustomStyleBox" <?php if (get_option('EIRD_UseCustomStyle') != 'true') {
    echo 'style="display:none"';
}
?>>
        <th>استایل سفارشی</th>
        <td>
          <textarea style="width: 90%;min-height: 400px;direction:ltr;" name="EIRD_CustomStyle" id="EIRD_CustomStyle"><?php echo get_option('EIRD_CustomStyle') ?></textarea><br>
        </td>
      </tr>

    </tbody>
  </table>
  <p class="submit"><input type="submit" value="به روز رسانی تنظیمات" class="button button-primary" id="submit" name="submit"></p>
</form>

<script>
  if(typeof jQuery == 'function')
  {
    jQuery("#EIRD_UseCustomStyle").change(function(){
      if(jQuery("#EIRD_UseCustomStyle").prop('checked') == true)
        jQuery("#EIRD_CustomStyleBox").show(500);
      else
        jQuery("#EIRD_CustomStyleBox").hide(500);
    });
  }
</script>

