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
// template vars $args
// - 'subject' - string
// - 'counter' - int = report id
// - 'revenues' - array with campaignSlug => revenue, string => float
// - 'startDate' - \DateTime
// - 'endDate' - \DateTime
// - 'sum' - sum of all campaign revenues, float
// - 'isRegular' - boolean, indicate if report was triggered manually
// - 'totalOrderCount' - float
// - 'pluginInstance' - an object instance of a DonationPluginInterface
// - 'shopName' - string
// - 'shopUrl' - string
// - 'shopSystem' - string
/* @var $args array */
?>
<h1><?php echo $args['subject'] ?></h1>

<h3>Zeitraum</h3>
<table style="border: 2px solid #000;">
    <tbody>
    <tr>
        <td style="border: 1px solid #eee;padding-left: 12px;"><strong>Von</strong></td>
        <td style="border: 1px solid #eee;padding-left: 12px;"><?php echo $args['startDate']->format(DateTime::RFC2822) ?></td>
    </tr>
    <tr>
        <td style="border: 1px solid #eee;padding-left: 12px;"><strong>Ende</strong></td>
        <td style="border: 1px solid #eee;padding-left: 12px;"><?php echo $args['endDate']->format(DateTime::RFC2822) ?></td>
    </tr>
    <tr>
        <td style="border: 1px solid #eee;padding-left: 12px;"><strong>Bestellungen insgesamt im Zeitraum</strong></td>
        <td style="border: 1px solid #eee;padding-left: 12px;"><?php echo $args['totalOrderCount'] ?></td>
    </tr>
    </tbody>
</table>

<h3>Bericht #<?php echo $args['counter'] ?></h3>
<table style="border: 2px solid #000;width: 100%;">
    <tbody>
    <?php foreach ($args['revenues'] as $slug => $revenue) : ?>
        <tr>
            <td style="border: 1px solid #eee;padding-left: 12px;" width="70%">
                <strong><?php echo $args['pluginInstance']->getCharityProductManagerInstance()->getCampaignBySlug($slug)->getName() ?></strong>
            </td>
            <td style="border: 1px solid #eee;padding-left: 12px;" width="30%">
                <?php echo number_format($revenue, 2) ?> &euro;
            </td>
        </tr>
    <?php endforeach ?>
    <tr style="font-size: 120%;">
        <td style="border: 1px solid #eee;border-top: 2px solid #000;padding-left: 12px;"><strong>Summe:</strong></td>
        <td style="border: 1px solid #eee;border-top: 2px solid #000;padding-left: 12px;">
            <strong><?php echo number_format($args['sum'], 2) ?> &euro;</strong>
        </td>
    </tr>
    </tbody>
</table>

<p style="margin-top: 12px;">
    <strong>Bericht erstellt am:</strong> <?php echo gmdate('F j, Y H:i:s') ?><br/>
    <strong>Bericht manuell erstellt:</strong> <?php echo $args['isRegular'] ? 'Nein' : 'Ja' ?><br/>
    <strong>Bericht erstellt mit:</strong> <?php echo $args['shopSystem'] ?>
</p>

<p>
    <a href="<?php echo $args['shopUrl'] ?>">Link zum Shop | <?php echo $args['shopName'] ?></a><br/>
</p>