<?php declare(strict_types=1);
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


namespace WWFDonationPlugin\Entity;


use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class DonationReportEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $orderCounter;

    /**
     * @var string
     */
    protected $intervalMode;

    /**
     * @var bool
     */
    protected $isRegular;

    /**
     * @var float
     */
    protected $totalAmount;

    /**
     * @var string
     */
    protected $campaignDetails;

    /**
     * @var string
     */
    protected $mailContent;

    /**
     * @var \DateTime
     */
    protected $startDate;

    /**
     * @var \DateTime
     */
    protected $endDate;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getOrderCounter(): int
    {
        return $this->orderCounter;
    }

    /**
     * @param int $orderCounter
     */
    public function setOrderCounter(int $orderCounter): void
    {
        $this->orderCounter = $orderCounter;
    }

    /**
     * @return string
     */
    public function getIntervalMode(): string
    {
        return $this->intervalMode;
    }

    /**
     * @param string $intervalMode
     */
    public function setIntervalMode(string $intervalMode): void
    {
        $this->intervalMode = $intervalMode;
    }

    /**
     * @return bool
     */
    public function isRegular(): bool
    {
        return $this->isRegular;
    }

    /**
     * @param bool $isRegular
     */
    public function setIsRegular(bool $isRegular): void
    {
        $this->isRegular = $isRegular;
    }

    /**
     * @return float
     */
    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    /**
     * @param float $totalAmount
     */
    public function setTotalAmount(float $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }

    /**
     * @return string
     */
    public function getCampaignDetails(): string
    {
        return $this->campaignDetails;
    }

    /**
     * @param string $campaignDetails
     */
    public function setCampaignDetails(string $campaignDetails): void
    {
        $this->campaignDetails = $campaignDetails;
    }

    /**
     * @return string
     */
    public function getMailContent(): string
    {
        return $this->mailContent;
    }

    /**
     * @param string $mailContent
     */
    public function setMailContent(string $mailContent): void
    {
        $this->mailContent = $mailContent;
    }

    /**
     * @return \DateTime
     */
    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    /**
     * @param \DateTime $startDate
     */
    public function setStartDate(\DateTime $startDate): void
    {
        $this->startDate = $startDate;
    }

    /**
     * @return \DateTime
     */
    public function getEndDate(): \DateTime
    {
        return $this->endDate;
    }

    /**
     * @param \DateTime $endDate
     */
    public function setEndDate(\DateTime $endDate): void
    {
        $this->endDate = $endDate;
    }
}