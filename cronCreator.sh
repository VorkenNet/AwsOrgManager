
echo "> Activating Cron"
#write out current crontab
sudo crontab -l | sudo tee -a mycron #> /dev/null
# echo new cron into cron file
echo " 40 15 * * * php awsOrgSnapAccount.php -a 815604229474 -r eu-central-1 -j dailyBackUp > /var/log/AwsOrgCrond" | sudo tee -a mycron #> /dev/null
sudo rm -f  mycron
