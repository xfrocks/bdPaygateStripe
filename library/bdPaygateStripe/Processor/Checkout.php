<?php

class bdPaygateStripe_Processor_Checkout extends bdPaygateStripe_Processor_Common
{
	public function generateFormData($amount, $currency, $itemName, $itemId, $recurringInterval = false, $recurringUnit = false, array $extraData = array())
	{
		$this->_assertAmount($amount);
		$this->_assertCurrency($currency);
		$this->_assertItem($itemName, $itemId);
		$this->_assertRecurring($recurringInterval, $recurringUnit);

		$formAction = XenForo_Link::buildPublicLink('canonical:misc/stripe');
		$publicKey = $this->_getStripePublicKey();
		$bitcoin = $this->_getStripeBitcoin();
		$name = XenForo_Application::getOptions()->get('boardTitle');

		$visitor = XenForo_Visitor::getInstance();
		$email = $visitor->get('email');

		if (!empty($recurringInterval) AND !empty($recurringUnit))
		{
			if ($recurringInterval > 1)
			{
				$panelLabel = new XenForo_Phrase("bdpaygatestripe_subscribe_x_every_y_{$recurringUnit}s", array(
					'cost' => '{{amount}}',
					'length' => $recurringInterval
				));
			}
			else
			{
				$panelLabel = new XenForo_Phrase("bdpaygatestripe_subscribe_x_every_{$recurringUnit}", array('cost' => '{{amount}}'));
			}
		}
		else
		{
			$panelLabel = '';
		}
		$label = new XenForo_Phrase('bdpaygatestripe_call_to_action');
		$amountInCents = bdPaygateStripe_Helper_Api::getAmountInCent($amount, $currency);
		$_xfToken = XenForo_Visitor::getInstance()->get('csrf_token_page');

		$callbackUrl = $this->_generateCallbackUrl($extraData);
		$returnUrl = $this->_generateReturnUrl($extraData);
		$callbackUrl = bdPaygateStripe_Helper_Url::appendParams($callbackUrl, compact('returnUrl'));
		$callbackUrlEncoded = htmlentities($callbackUrl);

		$form = <<<EOF
<form action="{$formAction}" method="POST">
	<script
		src="https://checkout.stripe.com/checkout.js" class="stripe-button"
		data-key="{$publicKey}"
		data-bitcoin="{$bitcoin}"
		data-name="{$name}"

		data-email="{$email}"

		data-panel-label="{$panelLabel}"
		data-label="{$label}"
		data-description="{$itemName}"
		data-amount="{$amountInCents}"
		data-currency="{$currency}">
	</script>

	<input type="hidden" name="itemId" value="{$itemId}" />
	<input type="hidden" name="cents" value="{$amountInCents}" />
	<input type="hidden" name="currency" value="{$currency}" />
	<input type="hidden" name="recurringInterval" value="{$recurringInterval}" />
	<input type="hidden" name="recurringUnit" value="{$recurringUnit}" />
	<input type="hidden" name="_xfToken" value="{$_xfToken}" />
	<input type="hidden" name="redirect" value="{$callbackUrlEncoded}" />
</form>
EOF;

		return $form;
	}

}
