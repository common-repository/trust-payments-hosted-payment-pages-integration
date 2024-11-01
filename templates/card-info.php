<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 13/12/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="securetrading_moto">
    <fieldset id="wc-cc-form" class="wc-st-form wc-credit-card-form wc-payment-form"
              style="background:transparent;">
        <div class="form-row form-row-wide">
            <label>
                <?php esc_html_e('Cardholder Name'); ?> <span class="required">*</span>
            </label>
            <div>
                <input type="text"
                       placeholder="Cardholder Name"
                       maxlength="50"
                       autocomplete="off"
                       data-card-details="cardholder-name"
                />
            </div>
        </div>
        <div class="form-row form-row-wide">
            <label>
                <?php esc_html_e('Card Number'); ?> <span class="required">*</span>
            </label>
            <div>
                <input type="tel"
                       inputmode="numeric"
                       placeholder="0000 0000 0000 0000"
                       maxlength="20"
                       autocomplete="off"
                       data-card-details="card-number"
                />
            </div>
        </div>
        <div class="form-row form-row-wide">
            <label>
                <?php esc_html_e('Expiry Date (MMYY)'); ?> <span class="required">*</span>
            </label>
            <div>
                <input type="tel"
                       id="sage-expiry-date"
                       inputmode="numeric"
                       placeholder="MMYY"
                       maxlength="4"
                       autocomplete="off"
                       data-card-details="expiry-date"
                />
            </div>
        </div>
        <div class="form-row form-row-wide">
            <label>
                <?php esc_html_e('Card Code (CVC)'); ?> <span class="required">*</span>
            </label>
            <div>
                <input type="tel"
                       inputmode="numeric"
                       placeholder="123"
                       maxlength="4"
                       autocomplete="off"
                       data-card-details="security-code"
                />
            </div>
        </div>
        <div class="form-row form-row-last">
            <input type="hidden" data-card-details="card-identifier" name="sagepay[cardIdentifier]">
            <input type="hidden" data-card-details="merchant-sessionkey" name="sagepay[merchantSessionKey]">
        </div>
        <div class="clear"></div>
    </fieldset>
</div>
