## Roadmap

Below is our development roadmap for **GitSyncWP**:

### Phase 1: Core Features (MVP)
- [x] GitHub auth via personal access token
- [x] Fetch and select a repository
- [x] Backup full WordPress database as `.sql`
- [x] Commit & push WordPress files + DB dump
- [x] Admin panel with a “Backup Now” button
- [x] Last sync log display

### Phase 2: Usability Enhancements
- [ ] OAuth-based GitHub login (no manual token entry)
- [ ] UI for branch selection
- [ ] Ability to exclude files/folders with a customizable `.gitignore`
- [ ] Change detection and diff preview
- [ ] Sync notifications (email or in-dashboard)

### Phase 3: Advanced Backup Features
- [ ] Scheduled backups (daily, weekly, monthly)
- [ ] Store DB backup in date-specific branches (e.g., `backup-YYYY-MM-DD`)
- [ ] Automatic ZIP backup generation
- [ ] Restore functionality for previous backups
- [ ] Option to export DB only or files only

### Phase 4: Cloud & DevOps Extensions
- [ ] GitLab/Bitbucket support (optional integration)
- [ ] GitHub Actions integration for auto-deployment/testing
- [ ] Webhook notifications (email/Discord/Slack)

### Phase 5: Developer & Power User Tools
- [ ] WP CLI command integration (e.g., `wp gitsync backup`)
- [ ] Detailed debug/logging panel
- [ ] Extendable hooks and filters for custom workflows
- [ ] Auto-updates via GitHub releases