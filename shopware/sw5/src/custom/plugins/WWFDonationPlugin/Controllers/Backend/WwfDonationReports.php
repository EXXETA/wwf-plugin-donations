<?php
/*
 * Copyright 2020-2021 EXXETA AG, Marius Schuppert
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Class Shopware_Controllers_Backend_WwfDonationReports
 *
 * DO NOT add a php namespace in this file! Shopware 5 will take care of (auto-)loading this class.
 */
class Shopware_Controllers_Backend_WwfDonationReports extends \Shopware_Controllers_Backend_Application
{
    protected $model = \WWFDonationPlugin\Models\DonationReportEntity::class;
    protected $alias = 'wwf_donation_reports';

    public function indexAction()
    {
        parent::indexAction();
    }
}