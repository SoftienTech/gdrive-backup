![Image](https://user-images.githubusercontent.com/77449397/109387234-3a08eb00-7911-11eb-93e1-505c4a4246d5.png)

# Google Drive Backup Tool
**Plesk**, **cPanel** and **CyberPanel** have automatic Google Drive backup features, but some other alternatives such as **DirectAdmin** and **CWP** don't have this feature. If you want to upload backups (created by your hosting panel) to Google Drive, you can use this small tool.

## What Does This Tool Do?
Your hosting control panel periodically takes backups and stores them in a folder. When this tool runs, it zips all files in that folder and uploads that ZIP file to Google Drive.

## How to Use?
1. Download **backup.php** file from GitHub. [You can click here to download the file.](https://github.com/SoftienTech/gdrive-backup/archive/main.zip)
2. Upload the file to your web server using FTP. **Please put this file in a location folder only you can access.** For example: https://example.com/098f6bcd4621d373cade4e832627b4f6/backup.php
3. Open the file URL with using your browser, you will see an installation wizard. You should fill in all fields and click the *Submit* button. Look at [How to Configure?](#how-to-configure) if you don't know.
4. You will see a notice like the following, click **Click here** link. A new tab will open, sign in with the Google account linked to the Google Drive account. After login, you will see an authentication code in the screen. Copy it and close the tab, paste the code to **Google API Authorization Key** field. Click the *Submit* button.

![Image](https://user-images.githubusercontent.com/77449397/109388745-0b434280-791a-11eb-8174-4cb225b02191.png)

5. After installation, you will see *success notice* and **Cronjob URL Address** will appear on the page. You need to add a new cronjob in your hosting control panel. You can set it as once a day or once a week. Cron command should be like that: `curl https://example.com/098f6bcd4621d373cade4e832627b4f6/backup.php?cron=true`

## How to Configure?
The setup wizard asks you to enter 4 different information: **Google API Client ID**, **Google API Client Secret**, **Google Drive Folder ID**, **Server Backup Folder Path**.
You need a Google account. Log in with your Google account, and go to [Google Drive](https://drive.google.com/)'s website. Create **New** button, and choose **Folder** option. Enter a name for folder, and click the **Create** button.

![Image](https://user-images.githubusercontent.com/77449397/109387940-2cedfb00-7915-11eb-9635-74c3a7cba744.png)![image](https://user-images.githubusercontent.com/77449397/109387971-60c92080-7915-11eb-9ca6-553f14fd7304.png)

After the folder is created, double click folder. Copy page URL, get the part after **folders/**. For example, page url address: https://drive.google.com/drive/u/0/folders/N128Lw2sgvb7HvyT1twPPS0jz15F_iTL
You must get **N128Lw2sgvb7HvyT1twPPS0jz15F_iTL**, it's your **Google Drive Folder ID**.

**Server Backup Folder Path** depends on your web hosting control panel. In **DirectAdmin**, it's `/home/reseller_username/user_backups/`. Be sure that the backup script can access the server backup folder.

You have to create a Google API application to get **Google API Client ID** and **Google API Client Secret**.

Go to [Google API Console](https://console.developers.google.com/). Click **Create Project** button, enter a name for the project and click *Create* button.

![Image](https://user-images.githubusercontent.com/77449397/109389102-f7004500-791b-11eb-9c92-c3cbfc99e9f6.png)![Image](https://user-images.githubusercontent.com/77449397/109389152-48103900-791c-11eb-8739-1a2e51605766.png)

You must enable Google Drive API. [Click here](https://console.developers.google.com/apis/library/drive.googleapis.com), and click *Enable* button. After enabling, click Google logo to go home page.

![Image](https://user-images.githubusercontent.com/77449397/109389694-798a0400-791e-11eb-83b5-f2e5fd885635.png)

Click **OAuth Consent Screen** from left menu. Select *External* option and click *Create* button.

You will see a page like the following, fill required inputs. Click *Save and Continue* button.

![Image](https://user-images.githubusercontent.com/77449397/109389217-a9380c80-791c-11eb-85c7-046671ce98a0.png)

In second page (Scope), you don't need to change any setting. Click *Save and Continue* button. In **Test Users** page, you should add your email address as test user. Then click *Save and Continue*.

Click **Credentials** from left menu. Select *OAuth Client ID* option.

![Image](https://user-images.githubusercontent.com/77449397/109389323-2a8f9f00-791d-11eb-83bc-66b9cb7139d8.png)

Choose *Desktop App* and click *Create* button. You will see **Your Client ID** and **Your Client Secret**.
