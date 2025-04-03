# Projects for WordPress®

Create a beautifully structured, developer-first showcase directory for your WordPress® plugins, themes, and patterns - powered by GitHub.

## 🔧 What It Does

**Projects for WordPress®** adds a custom post type (`projects`) and taxonomy (`project-type`) to your WordPress® admin, allowing you to:

- Add plugins, themes, or patterns as projects.
- Connect each project to a GitHub repository.
- Automatically fetch and display data like:
  - Latest version
  - Stars, forks, issues
  - License and language
  - Download counts
- Create a public download URL: `/download/ID/`
- Track and sort download metrics
- Render archive and single views with theme fallbacks or built-in templates
- Customize display settings via a clean settings page
- Provide social sharing buttons using Tabler Icons
- Use built-in REST API endpoints for listing and querying project data

---

## Installation

### From GitHub

1. **Download the latest ZIP** from the [GitHub Releases page](https://github.com/robertdevore/projects-for-wordpress/releases).
2. Go to your WordPress® Dashboard → Plugins → Add New → Upload Plugin.
3. Upload the ZIP and activate the plugin.

### From [robertdevore.com](https://www.robertdevore.com)

1. Visit the official plugin page: [Projects for WordPress®](https://www.robertdevore.com/projects/projects-for-wordpress).
2. Download the ZIP.
3. Install it via the WordPress® dashboard like above.

---

## Usage

### Add a New Project

1. In the WordPress® admin, go to **Projects → Add New**.
2. Add your project title, description, and featured image.
3. Select a **Project Type**: Plugin, Theme, or Pattern.
4. In the sidebar, paste the GitHub Repository URL (e.g., `https://github.com/username/repo`).
5. Publish.

### Enable Download Link

Each project automatically gets a unique download endpoint:

Example:  
`https://example.com/download/123`

This URL fetches the latest `.zip` release from GitHub and increments the download count.

## Settings Overview

Navigate to **Projects → Settings** to configure:

### General Settings

- **GitHub API Token** – Recommended for authenticated requests (avoids GitHub rate limits).
- **Telemetry** – Toggle to help improve the plugin.

### Templates Settings

Enable/disable the following GitHub data in your project views:

- Version  
- Last Updated  
- License  
- Language  
- Downloads  
- Stars / Forks / Issues  
- GitHub Owner

### Archive Settings

Toggle components for the project archive view:

- Archive Title  
- Project Title  
- Project Excerpt  
- Project Buttons

## Connect Your GitHub Repo

To connect a project to GitHub:

1. Paste the full repo URL into the **GitHub URL** field when editing a Project.
2. Make sure your GitHub repo has a release with a `.zip` asset or a valid `zipball_url`.

### Optional: Add a GitHub API Token

To avoid hitting rate limits or improve reliability:

1. Go to GitHub → **Settings → Developer Settings → Personal Access Tokens**.
2. Generate a token (no scopes required).
3. Paste it into the settings screen under **GitHub API Token**.

## How to Zip the Plugin for GitHub Releases

When attaching the plugin to a GitHub release:

1. Zip **only the contents of the plugin folder**, not the parent folder.
2. Ensure `projects-for-wordpress.php` and `/includes`, `/admin`, `/templates`, `/assets` are at the root level of the ZIP.
3. Name the ZIP clearly (e.g. `projects-for-wordpress-1.0.0.zip`).
4. Go to your GitHub repo → **Releases** → **New Release**.
5. Tag the release with the version number (e.g., `1.0.0`).
6. Upload your correctly structured ZIP as a release asset.

Your users will be redirected to this file when they use the `/download/` endpoint.

## REST API Endpoints

### All Projects

```http
GET /wp-json/projects/v1/projects
```

Supports optional query params:

- `page`
- `per_page`

### Popular Projects
    
```http
GET /wp-json/projects/v1/popular
```

Returns the most downloaded projects.

## Template Integration

You can override the plugin templates in your theme:

- `single-projects.php`
- `archive-projects.php`
- `taxonomy-project-type.php`

Just copy the templates from the plugin's `/templates/` folder into your theme's root and customize.

## Social Sharing Buttons

Each project includes social sharing links for:

- Facebook
- Twitter/X
- LinkedIn
- Reddit
- Pinterest
- WhatsApp
- Email

## Developer Notes

- All public-facing code is translation-ready.
- Full support for Full Site Editing (FSE) and Classic themes.
- REST API ready and easily extendable.
- Clean separation of concerns: CPT/taxonomy, settings, metaboxes, API, styling.

## Support & Contributions

Have a feature request or bug report? Open an issue on [GitHub](https://github.com/robertdevore/projects-for-wordpress/issues) or contribute via pull request.

## License

Projects for WordPress® is licensed under the [GNU GPL v2+](http://www.gnu.org/licenses/gpl-2.0.txt).

## Author

Built and maintained by [Robert DeVore](https://www.robertdevore.com).

Follow on [Twitter](https://twitter.com/deviorobert) or sponsor development on [GitHub Sponsors](https://github.com/sponsors/robertdevore).
