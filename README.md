# 🔄 GitSyncWP – WordPress Backup to GitHub

**GitSyncWP** is a free, open-source WordPress plugin that automatically backs up your website files and database to a private (or public) GitHub repository. Protect your site from hacks, data loss, and accidental changes with version-controlled backups — all with a simple UI.

> 📦 Git-powered backups.  
> 🔒 Secure.  
> 🎛️ Easy to use.  
> 💥 Totally free forever.

---

## 🚀 Features

- 🔐 GitHub authentication (via token)
- 📁 Full WordPress file backup
- 💾 Database export as `.sql`
- ☁️ Automatic Git commit and push to selected GitHub repo
- 🕹️ Clean admin UI with "Backup Now" button
- 🗓️ WP-Cron support for scheduled backups (daily/weekly)
- 📋 Backup logs and sync history
- ⚙️ `.gitignore` support to exclude sensitive files

---

## 📸 Screenshots (Coming Soon)

<!-- Add screenshots here when UI is ready -->
- GitHub auth setup
- Repository selection screen
- Backup log page
- Settings UI

---

## 🧰 Installation

1. Upload the plugin to your `/wp-content/plugins/` directory.
2. Activate the plugin via **Plugins > GitSyncWP > Activate**.
3. Upon activation:
   - Enter your **GitHub Personal Access Token**
   - Select a repository from your account
   - Save settings
4. Click **"Backup Now"** to trigger your first sync!

---

## 🔧 Requirements

- PHP 7.4+
- WordPress 5.8+
- Git installed on your server (shell access)
- A GitHub repository with write access
- GitHub personal access token (classic or fine-grained)

---

## 🔐 Security Notes

- Tokens are stored using the WordPress options API (encrypted if possible).
- Use `.gitignore` to exclude sensitive files like `wp-config.php` or `/uploads`.
- All inputs are sanitized and validated.

---

## 📌 Roadmap

- [x] GitHub token authentication
- [x] Database dump and file sync
- [x] Manual backup via UI
- [ ] Scheduled backups (daily/weekly)
- [ ] GitHub OAuth login (no token needed)
- [ ] GitLab/Bitbucket support
- [ ] File restore from commit
- [ ] Email/Slack/Webhook notifications
- [ ] WP-CLI support: `wp gitsync backup`

See the full [ROADMAP.md](ROADMAP.md) for details.

---

## 👨‍💻 For Developers

- Modular structure (Classes: `GitHandler`, `DBHandler`, `GitHubAPI`)
- Hooks and filters planned for extending behavior
- Open to contributions and PRs!

---

## 👫 Contributing

We welcome contributions!  
To get started:

1. Fork the repo.
2. Create your feature branch:  
   ```bash
   git checkout -b feature/my-feature
   ```
3. Commit your changes.
4. Push and open a pull request.

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

---

## 📄 License

MIT License – Free for personal and commercial use.

---

## 🌍 About

Made with ❤️ by contributors from the open-source community.  
Aimed at making WordPress safer and Git-friendly for everyone.