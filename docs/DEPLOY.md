# Putting CMT Tender Hub on the DigitalOcean server

Server: **134.209.98.112** (Singapore, Ubuntu 24.04, 2 GB).
Web address: **https://tenderhub.134-209-98-112.sslip.io**

The first time takes about 45 minutes. Each box below is one command, or a few. Copy the whole box,
paste it, and press Enter. Where it says `CHANGE_ME`, type your own value.

**Where to type:**
- **"Server"** means the DigitalOcean Console: on your Droplet's page, click **Console** (top right).
  It opens a black window already signed in as `root`. To paste, use right-click → Paste, or Ctrl+Shift+V.
- **"PC"** means **PowerShell** on your Windows PC (Start → type `PowerShell`).

Never paste passwords into chat, email or GitHub. They only go into the server's `.env` file (step 3),
or are typed when the server asks for them.

---

## 1. Prepare the server (Server, about 5 minutes)

This does three things:
- installs updates and Docker
- adds 2 GB of "swap", spare memory on the disk used as a safety net
- switches on the firewall, so only web traffic and the console get in

```sh
apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
curl -fsSL https://get.docker.com | sh
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw allow 443/udp && ufw --force enable
```

If it asks about restarting services, press Enter to accept the defaults.

## 2. Give the server read-only access to the code (Server and GitHub, about 5 minutes)

The code is in a private GitHub repository, so the server needs its own key to read it.

```sh
ssh-keygen -t ed25519 -f ~/.ssh/github -N "" -C "tenderhub-server"
printf 'Host github.com\n  IdentityFile ~/.ssh/github\n' >> ~/.ssh/config
cat ~/.ssh/github.pub
```

Copy the line it prints (it starts with `ssh-ed25519`). Then, on GitHub:
1. Open **github.com/nurlynnda/cmt-tender-hub → Settings → Deploy keys → Add deploy key**.
2. Title: `DigitalOcean server`. Paste the line into Key. **Leave "Allow write access" unticked.**
3. Click Add key.

Back on the server, download the code. When asked "Are you sure you want to continue connecting?", type `yes`:

```sh
git clone git@github.com:nurlynnda/cmt-tender-hub.git ~/cmt-tender-hub
cd ~/cmt-tender-hub
```

## 3. Fill in the server's settings (Server, about 10 minutes)

**a. Make two strong database passwords and the app's secret key.** Run this, then keep the window open;
you'll copy the three lines into the settings file next.

```sh
echo "DB_PASSWORD=$(openssl rand -hex 16)"; echo "DB_ROOT_PASSWORD=$(openssl rand -hex 16)"; echo "APP_KEY=base64:$(openssl rand -base64 32)"
```

**b. Open the settings file:**

```sh
cd ~/cmt-tender-hub && cp .env.production.example .env && nano .env
```

**c. In the editor**, use the arrow keys to move around:
- **`APP_KEY=`**: paste the `APP_KEY=base64:…` value from step a.
- **`DB_PASSWORD`** and **`DB_ROOT_PASSWORD`**: paste the two values from step a.
- **Email**, for "forgot password". Use your company email account:

| Company email is… | MAIL_HOST | MAIL_PORT | MAIL_USERNAME | MAIL_PASSWORD |
|---|---|---|---|---|
| Google Workspace (Gmail) | `smtp.gmail.com` | `587` | the full email address | an **App Password**, not the normal password: Google Account → Security → 2-Step Verification (must be on) → App passwords |
| Microsoft 365 (Outlook) | `smtp.office365.com` | `587` | the full email address | the account password (your IT admin may need to switch on "Authenticated SMTP" for this mailbox) |
| Hosting provider email (cPanel etc.) | as given by the provider, often `mail.yourdomain.com` | `587` | the full email address | the mailbox password |

Set `MAIL_FROM_ADDRESS` to the same email address.

**d. Save and close:** press Ctrl+O, then Enter (save), then Ctrl+X (exit).

## 4. Start the app (Server, about 10 minutes the first time)

```sh
cd ~/cmt-tender-hub && chmod +x docker/prod/*.sh && docker compose -f docker-compose.prod.yml up -d --build
```

The first build takes 5–10 minutes. When it finishes, check that all four parts say "Up" (`app` and `mysql` also say "healthy"):

```sh
docker compose -f docker-compose.prod.yml ps
```

Open **https://tenderhub.134-209-98-112.sslip.io** in your browser. You should see the sign-in page with the padlock (HTTPS).
**Don't sign in yet.** The database is still empty; your data comes next.

## 5. Move your data from the PC to the server (PC, then Server, about 10 minutes)

**a. On the PC**, make sure Docker Desktop is running, then export the database:

```powershell
cd C:\Projects\cmt-tender-hub
docker compose exec -T mysql sh -c 'mysqldump -uroot -p$MYSQL_ROOT_PASSWORD --single-transaction --quick --no-tablespaces tender_hub | gzip > /tmp/tender_hub.sql.gz'
docker compose cp mysql:/tmp/tender_hub.sql.gz .\tender_hub.sql.gz
```

(A line saying "Using a password on the command line interface can be insecure" is normal here.)

**b. Still on the PC**, copy it to the server. It asks for the server's root password (the one you chose
when creating the Droplet); type it and press Enter. Nothing shows while you type, which is normal.
The first time, type `yes` to trust the server.

```powershell
scp .\tender_hub.sql.gz root@134.209.98.112:/root/
```

**c. On the server**, load it in (about 2 minutes) and restart the app:

```sh
cd ~/cmt-tender-hub
gunzip -c /root/tender_hub.sql.gz | docker compose -f docker-compose.prod.yml exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker compose -f docker-compose.prod.yml restart app worker scheduler
rm /root/tender_hub.sql.gz
```

**d. On the PC**, delete the export file (it holds all your data) and stop the PC's own daily collection,
so the server is the only one collecting from now on:

```powershell
Remove-Item .\tender_hub.sql.gz
docker compose stop scheduler worker
```

If you uploaded a company stamp in Finance Settings, upload it again on the server.

## 6. Lock down the two sample accounts (Server, 2 minutes). Do this right away.

`admin@cmt.test` and `manager@cmt.test` came from the development data and still use the development
password, which is written in the code. Give each a new password. You type it twice, and nothing shows while you type:

```sh
cd ~/cmt-tender-hub
docker compose -f docker-compose.prod.yml exec app php artisan users:set-password admin@cmt.test
docker compose -f docker-compose.prod.yml exec app php artisan users:set-password manager@cmt.test
```

Then sign in at https://tenderhub.134-209-98-112.sslip.io as `admin@cmt.test` with the new password.
- In **Manage Users**, give people their real email addresses, including the 10 switched-off staff
  accounts from the register import. Each person can then use "Forgot password?" to set their own password.
- Once you have your own real admin account, you can switch off `admin@cmt.test` and `manager@cmt.test`.

## 7. Daily backup (Server, 1 minute)

Every night at 2am Malaysia time the server saves a copy of the database in `~/cmt-tender-hub/backups`,
keeping the last 7 days:

```sh
(crontab -l 2>/dev/null; echo "0 18 * * * /root/cmt-tender-hub/docker/prod/backup.sh >> /root/cmt-tender-hub/backups/backup.log 2>&1") | crontab -
cd ~/cmt-tender-hub && ./docker/prod/backup.sh
```

The second line makes a first backup now, to check that it works.
- **Extra safety (recommended, about US$2.40 a month):** on the Droplet page → **Backups** → Enable.
  DigitalOcean then keeps weekly copies of the whole server *outside* it, so a deleted or broken
  server can be brought back.
- **Copy a backup to your PC (PC):** `scp root@134.209.98.112:/root/cmt-tender-hub/backups/tender_hub-*.sql.gz .`

## 8. Check it works

- [ ] Sign in, open **Find Tenders**, **Market Insights** and a tender's **Costing**.
- [ ] Use **Forgot password?** with a real email address and check the email arrives.
- [ ] The next day, after 12:01pm, Find Tenders says "Last collected …" with today's date.

---

## Later: updating to a new version

When there's a new version on GitHub's `main`, on the **Server** run:

```sh
cd ~/cmt-tender-hub && ./docker/prod/update.sh
```

It backs up the database, downloads the new code, rebuilds and restarts. Your data stays.
The site is unavailable for about a minute while it restarts.

## Useful commands (Server, inside `~/cmt-tender-hub`)

| To… | Run |
|---|---|
| See if everything is running | `docker compose -f docker-compose.prod.yml ps` |
| See recent errors from the website | `docker compose -f docker-compose.prod.yml logs --tail=100 app` |
| See the daily collection's messages | `docker compose -f docker-compose.prod.yml logs --tail=100 worker` |
| Restart everything | `docker compose -f docker-compose.prod.yml restart` |
| Check memory use | `free -h` and `docker stats --no-stream` |

## Restoring a backup

**This replaces everything in the server's database with the backup.** Pick the file by date:

```sh
cd ~/cmt-tender-hub && ls backups
gunzip -c backups/tender_hub-CHANGE_ME.sql.gz | docker compose -f docker-compose.prod.yml exec -T mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker compose -f docker-compose.prod.yml restart app worker scheduler
```

## If the server's IP address ever changes

This only happens if the Droplet is deleted and recreated. The web address contains the IP address, so:
1. In `.env`, change `APP_HOST` and `APP_URL` to the new address (`tenderhub.NEW-IP-WITH-DASHES.sslip.io`).
2. Run `docker compose -f docker-compose.prod.yml up -d`.
