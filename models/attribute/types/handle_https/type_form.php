<?php defined('C5_EXECUTE') or die('Access Denied.');

$fh = Loader::helper('form');
/* @var $fh FormHelper */

$akEnabled = empty($akEnabled) ? false : true;
$akRedirectEditors = empty($akRedirectEditors) ? false : true;
$akCustomDomains = empty($akCustomDomains) ? false : true;
?>
<fieldset>
    <legend><?php echo t('Type Options')?></legend>
    <div class="clearfix control-group">
        <label class="control-label" for="akEnabled"><?php echo t('Enable HTTP/HTTPS handling')?></label>
        <div class="input controls">
            <?php echo $fh->checkbox('akEnabled', 1, $akEnabled); ?>
        </div>
    </div>
    <div class="clearfix control-group" <?php echo $akEnabled ? '' : ' style="display: none"'; ?>>
        <label class="control-label" for="akRedirectEditors"><?php echo t('Redirect users that can edit the page')?></label>
        <div class="input controls">
            <?php echo $fh->checkbox('akRedirectEditors', 1, $akRedirectEditors); ?>
        </div>
    </div>
    <div class="clearfix control-group" <?php echo $akEnabled ? '' : ' style="display: none"'; ?>>
        <label class="control-label" for="akDefaultRequirement"><?php echo t('Default behaviour')?></label>
        <div class="input controls">
            <?php echo $fh->select('akDefaultRequirement', $akValidDefaultRequirements, $akDefaultRequirement); ?>
        </div>
    </div>
    <div class="clearfix control-group" <?php echo $akEnabled ? '' : ' style="display: none"'; ?>>
        <label class="control-label" for="akCustomDomains"><?php echo t('HTTP and HTTPS domains are different?')?></label>
        <div class="input controls">
            <?php echo $fh->checkbox('akCustomDomains', 1, $akCustomDomains); ?>
        </div>
    </div>
    <div class="clearfix control-group" <?php echo ($akEnabled && $akCustomDomains) ? '' : ' style="display: none"'; ?>>
       <label class="control-label" for="akHTTPDomain"><?php echo t('HTTP base url')?></label>
        <div class="input controls">
          <?php echo $fh->url('akHTTPDomain', $akHTTPDomain, array('class' => 'span4', 'placeholder' => 'http://')) ?>
          (<?php echo t('please specify only the domain'); ?>)
        </div>
    </div>
    <div class="clearfix control-group" <?php echo ($akEnabled && $akCustomDomains) ? '' : ' style="display: none"'; ?>>
       <label class="control-label" for="akHTTPSDomain"><?php echo t('HTTPS base url')?></label>
        <div class="input controls">
          <?php echo $fh->url('akHTTPSDomain', $akHTTPSDomain, array('class' => 'span4', 'placeholder' => 'https://')) ?>
          (<?php echo t('please specify only the domain'); ?>)
        </div>
    </div>
</fieldset>
<script>
$(document).ready(function() {
    function update()
    {
        var akEnabled = $('#akEnabled').is(':checked'), akCustomDomains = $('#akCustomDomains').is(':checked');
        $('#akRedirectEditors,#akDefaultRequirement,#akCustomDomains').closest('div.control-group')[akEnabled ? 'show' : 'hide']('fast');
        $('#akHTTPDomain,#akHTTPSDomain').closest('div.control-group')[(akEnabled && akCustomDomains) ? 'show' : 'hide']('fast');
        if (akEnabled && akCustomDomains) {
            $('#akHTTPDomain,#akHTTPSDomain').attr('required', 'required');
            $('#akHTTPDomain').attr('pattern', '^http://[^/]+$');
            $('#akHTTPSDomain').attr('pattern', '^https://[^/]+$');
        } else {
            $('#akHTTPDomain,#akHTTPSDomain').removeAttr('required', 'required').removeAttr('pattern');
        }
    }
    update();
    $('#akEnabled,#akCustomDomains').on('change', function () { update(); });
});
</script>
