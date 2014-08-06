<?php

class bdPaygateStripe_bdPaygate_Model_Processor extends XFCP_bdPaygateStripe_bdPaygate_Model_Processor
{
	public function getProcessorNames()
	{
		$names = parent::getProcessorNames();

		$names['stripe'] = 'bdPaygateStripe_Processor_Checkout';

		return $names;
	}

}
