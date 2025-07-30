# mysqldump_local_backup.sh
# spustit přes Plánvoač úloh ve windows

DATE=$(date +"%Y-%m-%d")
mysqldump -h sql113.byethost15.com -u b15_39452825 -p'5761VkRpAk' b15_39452825_KremlikDatabase01 > backups/db_$DATE.sql

cd /Matrix/Polyglot
cp backups/db_$DATE.sql ./db/latest_backup.sql
git add ./db/latest_backup.sql
git commit -m "DB backup on $DATE"
git push


# DATE=$(date +"%Y-%m-%d")
# mysqldump -h sqlXXX.byethost.com -u YOUR_USERNAME -p'YOUR_PASSWORD' YOUR_DB_NAME > backups/db_$DATE.sql

# cd /path/to/your/git/repo
# cp backups/db_$DATE.sql ./db/latest_backup.sql
# git add ./db/latest_backup.sql
# git commit -m "DB backup on $DATE"
# git push
