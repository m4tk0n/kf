# Live server snapshot

The workflow `.github/workflows/server-snapshot.yml` reads the public text-file API at:

`https://7x.wz.cz/_kf/ai/?action=list`

It downloads each listed text file through the individual `?action=txt&file=...` endpoint and publishes the result to the separate `server-snapshot` branch. It runs after a successful FTP deployment, can be started manually in GitHub Actions, and also refreshes hourly.

The snapshot excludes `.env`, `.env.*`, `.git`, `.github`, and `ai-backups`. Binary files that the TXT endpoint refuses are skipped. The repository is public, so this branch is public too; it contains only files already exposed by the public AI Code Viewer.

The workflow can also be triggered by updating this documentation file.

Snapshot workflow trigger refreshed for server/GitHub comparison.
