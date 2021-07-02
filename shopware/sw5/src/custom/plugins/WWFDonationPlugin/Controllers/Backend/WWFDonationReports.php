<?php

/**
 * Class Shopware_Controllers_Backend_WWFDonationReports
 *
 * DO NOT add a php namespace in this file! Shopware 5 will take care of (auto-)loading this class.
 */
class Shopware_Controllers_Backend_WWFDonationReports extends \Shopware_Controllers_Backend_Application
{
    protected $model = \WWFDonationPlugin\Entity\DonationReportEntity::class;
    protected $alias = 'wwf_donation_reports';

    public function indexAction()
    {
        parent::indexAction();
    }
}