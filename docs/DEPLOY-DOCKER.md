# በዶከር አዲስ መጫን — `/var/www/permit-pro`

ይህ መመሪያ አዲሱን ሲስተም በዶከር በ`/var/www/permit-pro` ይጭናል፤ አሁን ያለው
(`/var/www/Heavy`) እስከ መጨረሻው ደረጃ ድረስ **ሳይነካ** መሥራቱን ይቀጥላል። ያሉት
መረጃዎች (ዳታቤዝና የተሰቀሉ ፋይሎች) ወደ አዲሱ ይገለበጣሉ እንጂ አይሰረዙም።

`<...>` ያለበትን ሁሉ በራስዎ ይተኩ። ትዕዛዞቹ በሰርቨሩ ላይ (Ubuntu 24.04) በ`administrator`
ተጠቃሚ ይሮጣሉ።

---

## 1. ዶከርን መጫን (አንድ ጊዜ ብቻ)

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-v2 docker-buildx
sudo usermod -aG docker administrator
```

ከዚያ ከሰርቨሩ **ወጥተው እንደገና ይግቡ** (ssh)፤ ከዚያ ይፈትሹ፦

```bash
docker compose version
ss -ltnp | grep ':8080 ' || echo "8080 ነፃ ነው"
```

`8080` ሌላ ፕሮግራም ከያዘው በደረጃ 3 `HTTP_PORT` ላይ ሌላ ነፃ ቁጥር ይምረጡ (ለምሳሌ `8090`)።

## 2. ኮዱን ማምጣት

```bash
cd /var/www/permit-pro
git clone -b production https://github.com/erkihunb/Heavy.git .
```

GitHub ተጠቃሚ ስምና ይለፍ ቃል ከጠየቀ፣ ይለፍ ቃሉ ቦታ **Personal Access Token** ይጠቀሙ
(GitHub → Settings → Developer settings → Tokens፤ `repo` የማንበብ ፈቃድ ብቻ)።

## 3. ቅንብሮቹን (`.env.production`) ማዘጋጀት

```bash
cp .env.production.example .env.production
grep -E "^(APP_KEY|APP_URL|PERMITS_VERIFY_URL|MAIL_|PERMITS_STAFF_TWO_FACTOR_REQUIRED|SMS_)" /var/www/Heavy/.env
openssl rand -base64 24   # ሶስት ጊዜ ይሩጡ፦ ለDB_PASSWORD፣ DB_ROOT_PASSWORD፣ REDIS_PASSWORD
nano .env.production
```

በ`.env.production` ውስጥ እነዚህን ይሙሉ፦

| ቅንብር | ምን እንደሚገባ |
|---|---|
| `APP_KEY` | **ከ`/var/www/Heavy/.env` ያለውን በትክክል ይገልብጡ።** አዲስ ቁልፍ ከፈጠሩ የቆዩ መረጃዎች (የሁለት-ደረጃ መግቢያ ኮዶች፣ የተመሰጠሩ መስኮች) አይነበቡም። |
| `APP_URL` | `https://permit.aacra.gov.et` |
| `PERMITS_VERIFY_URL` | ከአሮጌው `.env` ያለውን ይገልብጡ (ባዶ ከሆነ `APP_URL`)። የታተሙት QR ኮዶች ይህንን አድራሻ ይጠቁማሉ። |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REDIS_PASSWORD` | በ`openssl` የተፈጠሩት አዲስ ይለፍ ቃሎች |
| `MAIL_*`, `SMS_*` | ከአሮጌው `.env` ይገልብጡ |
| `HTTP_PORT` | `8080` (ወይም በደረጃ 1 የመረጡት ነፃ ቁጥር) |

`HTTP_BIND=127.0.0.1` እንዳለ ይተዉት፤ ድረ-ገጹ ከውጭ የሚደረሰው በአሁኑ nginx በኩል ብቻ ነው።

```bash
chmod 600 .env.production
```

## 4. መገንባትና ማስነሳት

```bash
cd /var/www/permit-pro
docker compose -f compose.production.yaml --env-file .env.production up -d --build
docker compose -f compose.production.yaml --env-file .env.production ps
```

የመጀመሪያው ግንባታ ከ10–20 ደቂቃ ሊወስድ ይችላል። ሁሉም አገልግሎቶች `running` / `healthy` መሆን አለባቸው።

## 5. ያሉትን መረጃዎች መገልበጥ

የአሮጌው ዳታቤዝ ስምና ተጠቃሚ በ`/var/www/Heavy/.env` (`DB_DATABASE`, `DB_USERNAME`) ውስጥ አለ።

**5.1 — አሮጌውን ለአጭር ጊዜ "ጥገና ላይ" ማድረግና backup መውሰድ** (በዚህ ጊዜ ማንም አዲስ መረጃ አያስገባም)፦

```bash
cd /var/www/Heavy
sudo -u www-data php8.4 artisan down
mkdir -p /var/www/permit-pro/backups
set -o pipefail
export MYSQL_PWD="$(sudo grep -E '^DB_PASSWORD=' /var/www/Heavy/.env | cut -d= -f2- | sed -E 's/^"//; s/"$//')"
mysqldump -h 127.0.0.1 -P 3306 -u <DB_USERNAME> --single-transaction --quick --triggers \
    --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 <DB_DATABASE> \
  | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' \
  | gzip -9 | sudo tee /var/www/permit-pro/backups/db-import.sql.gz > /dev/null && echo "DUMP OK"
unset MYSQL_PWD
sudo tar -czf /var/www/permit-pro/backups/files-import.tar.gz -C /var/www/Heavy/storage app
ls -lh /var/www/permit-pro/backups
```

ሁለቱም ፋይሎች ከ0 የበለጠ መጠን ሊኖራቸው ይገባል። **እነዚህ ፋይሎች የአሮጌው ሲስተም backupም ናቸው፤ አይሰርዟቸው።**

**5.2 — ወደ አዲሱ መጫን**፦

```bash
cd /var/www/permit-pro
C="docker compose -f compose.production.yaml --env-file .env.production"
$C stop app worker scheduler web
$C exec backup /bin/bash /backup/restore.sh /backups/db-import.sql.gz /backups/files-import.tar.gz
# The files came from the old server's www-data; the image's www-data has another number.
$C run --rm -u root app chown -R www-data:www-data /var/www/html/storage
$C start app worker scheduler web
$C run --rm app php artisan migrate --force
$C run --rm app php artisan db:seed --force
```

`migrate` የሚጨምረው አዲሶቹን ሰንጠረዦችና ፐርሚሽኖች ብቻ ነው፤ ምንም አይሰርዝም።

## 6. ከመቀየርዎ በፊት መፈተሽ

```bash
curl -fsS http://127.0.0.1:8080/up && echo " — ሲስተሙ ይሠራል"
```

ከራስዎ ኮምፒውተር በbrowser ለማየት (ድረ-ገጹን ሳይቀይሩ) በssh tunnel ይግቡ፦

```powershell
ssh -L 8080:127.0.0.1:8080 administrator@<የሰርቨሩ-አድራሻ>
```

ከዚያ `http://localhost:8080` ይክፈቱ፣ በራስዎ መለያ ይግቡ፣ የቆዩ ፈቃዶችና ደብዳቤዎች
መኖራቸውን ያረጋግጡ፣ አንድ ሰርቲፊኬት ያውርዱ።

## 7. `permit.aacra.gov.et`ን ወደ አዲሱ መቀየር

```bash
sudo cp /etc/nginx/sites-enabled/<የpermit-ፋይል> ~/permit-nginx-old.conf   # ለመመለስ
sudo nano /etc/nginx/sites-enabled/<የpermit-ፋይል>
```

በ`permit.aacra.gov.et` የ`server { ... }` ክፍል (የ443/SSL ያለው) ውስጥ፦ `root ...;`፣
`index ...;` እና ሁሉንም `location ... { ... }` ክፍሎች አጥፍተው በዚህ ይተኩ (የSSL መስመሮቹን
እንዳሉ ይተዉ)፦

```nginx
    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
    }
```

```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl disable --now heavy-queue.service
sudo crontab -l -u www-data 2>/dev/null | grep -n artisan    # አሮጌ scheduler ካለ ይኸው
```

የአሮጌው scheduler መስመር ካለ በ`sudo crontab -e -u www-data` በመስመሩ መጀመሪያ `#` በማድረግ
ያጥፉት፤ አዲሱ ሲስተም የራሱ scheduler አለው።

አሁን `https://permit.aacra.gov.et` ይክፈቱና ይፈትሹ።

## ችግር ቢፈጠር — ወደ አሮጌው መመለስ

```bash
sudo cp ~/permit-nginx-old.conf /etc/nginx/sites-enabled/<የpermit-ፋይል>
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable --now heavy-queue.service
cd /var/www/Heavy && sudo -u www-data php8.4 artisan up
```

አሮጌው ሲስተምና ዳታቤዙ አልተቀየሩም፤ ከ5.1 በኋላ የገቡ መረጃዎች ካሉ ብቻ እነሱ በአዲሱ ውስጥ ይቀራሉ።

## ወደፊት ማዘመን

```bash
cd /var/www/permit-pro
C="docker compose -f compose.production.yaml --env-file .env.production"
$C exec backup /bin/bash /backup/backup.sh
git pull origin production
$C up -d --build
$C run --rm app php artisan migrate --force
$C run --rm app php artisan db:seed --force
```

የዕለት backup በራሱ ይወሰዳል (`backups/` ፎልደር፣ ለ30 ቀን ይቆያል)።
