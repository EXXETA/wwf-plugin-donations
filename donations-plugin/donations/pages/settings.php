<div class="wrap">
    <h2>Spenden-Einstellungen</h2>

    <?php
    if (!current_user_can('manage_options')) {
        echo "Diesem Benutzer fehlen die Berechtigungen, um die Spendeneinstellungen zu ändern.";
        return;
    }
    $currentReportingInterval = \donations\SettingsManager::getOptionCurrentReportingInterval();
    $currentLiveReportsDaysInPast = \donations\SettingsManager::getOptionLiveReportDaysInPast();
    $reportRecipient = \donations\SettingsManager::getOptionReportRecipientMail();
    ?>
    <form action="options.php" method="post">
        <?php settings_fields('wp_donations'); ?>

        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row">Reporting Einstellungen</th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>Reporting Einstellungen</span>
                        </legend>

                        <label for="wp_donations_reporting_interval_select">
                            Regelmäßige Spendenberichte generieren
                        </label>
                        <select id="wp_donations_reporting_interval_select"
                                class="postform"
                                name="wp_donations_reporting_interval">
                            <?php foreach (\donations\SettingsManager::getReportingIntervals() as $value => $label): ?>
                                <option value="<?php esc_attr_e($value) ?>"
                                    <?php
                                    echo $currentReportingInterval == $value ? ' selected="selected"' : '';
                                    ?>
                                ><?php esc_attr_e($label) ?></option>
                            <?php endforeach ?>
                        </select>
                        <br/>
                        <br/>

                        <label for="wp_donations_reporting_live_days_in_past_input">
                            Live-Berichte &ndash; Tage in die Vergangenheit
                        </label>
                        <input type="number" id="wp_donations_reporting_live_days_in_past_input"
                               name="wp_donations_reporting_live_days_in_past" min="1" max="150" step="1"
                               class="small-text"
                               value="<?php echo $currentLiveReportsDaysInPast ?>"/>
                        <br/>
                        <br/>

                        <label for="wp_donations_reporting_recipient_mail_field">
                            Empfangsadresse der Spendenberichte
                        </label>
                        <input id="wp_donations_reporting_recipient_mail_field" type="email" readonly
                               <?php
                               // do not make it too easy to change mail:
                               // name="wp_donations_reporting_recipient"
                               ?> size="75" value="<?php echo $reportRecipient ?>"/>
                    </fieldset>
                </td>
            </tr>
            </tbody>
        </table>
        <?php submit_button('Einstellungen speichern'); ?>
    </form>
</div>