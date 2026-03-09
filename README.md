[client]: https://github.com/ushahidi/platform-client
[download]: https://github.com/ushahidi/platform-release/releases
[setup-guides]: https://docs.ushahidi.com/platform-developer-documentation/development-and-code/setup_alternatives
[support]: https://www.ushahidi.com/support
[rest-api-docs]: https://docs.ushahidi.com/platform-developer-documentation/tech-stack/api-documentation
[getin]: https://www.ushahidi.com/support/get-involved
[issues]: https://github.com/ushahidi/platform/issues
[ush2]: https://github.com/ushahidi/Ushahidi_Web
[ushahidi]: http://ushahidi.com

Ushahidi Platform
=================

## What is Ushahidi Platform?

Ushahidi Platform is an open source web application for information collection, visualization and interactive mapping. It helps you to collect info from: SMS, Twitter, RSS feeds, Email. It helps you to process that information, categorize it, geo-locate it and publish it on a map.

This repository contains the backend code with the REST API implementation.

Head over to the [Platform Client repository][client] for the browser app code.

## Setup essentials

The shortest path to get up and running is:

- Install Docker Engine
- Install Make command (parses Makefile)
- Run `make start`

The backend API will be reachable at **`http://localhost:8080`** from the same machine and at
**`http://<your-machine-ip>:8080`** from any other device on your local network.

> **What about the browser client application?**

> Once your Platform backend is running, head over to the [platform-client-mzima](https://github.com/ushahidi/platform-client-mzima) repository to get the in-browser Platform experience!

### Running the client accessible from your local network

By default the `@ushahidi/platform-client-mzima` development server (Angular CLI) listens only on
`localhost` and is **not reachable from other devices** on your local network.

To expose it on all network interfaces, pass the `--host 0.0.0.0` flag to Angular CLI:

```bash
# inside the platform-client-mzima repository
npm run web:serve -- --host 0.0.0.0
```

You can then open the client on another device using your machine's local IP address, e.g.
`http://192.168.1.x:4200`.

> **Tip:** make sure the `BACKEND_URL` variable in the client's `.env` file also points to your
> machine's local IP (`http://192.168.1.x:8080`) instead of `http://localhost:8080`, so that the
> browser (on the other device) can reach the API.

### Default admin account

After running `make start` the database migrations automatically create a default admin account:

| Field    | Value               |
|----------|---------------------|
| Email    | `admin@example.com` |
| Password | `admin`             |

> **Security notice:** Change this password immediately after your first login, especially in any
> non-local or production environment.

### Creating a new admin account

Use the built-in `artisan user:create` command to add extra admin (or regular) accounts at any time.

**With Docker (recommended):**

```bash
# Interactive — you will be prompted for missing fields
make create-admin

# Non-interactive — pass all fields directly
docker compose exec platform php artisan user:create \
  --email=you@example.com \
  --password=yourpassword \
  --realname="Your Name" \
  --role=admin
```

**Without Docker (bare-metal / production):**

```bash
php artisan user:create \
  --email=you@example.com \
  --password=yourpassword \
  --realname="Your Name" \
  --role=admin
```

To create a regular (non-admin) user, omit `--role` or pass `--role=user`.

### Other helpful commands

You may use `make start` to restart the containers (does a full container build).

You may use `make apply` to apply dependency and migration changes to containers (without full container build). **Note:** this requires containers to be up.
​
To stop Docker containers run `make stop`

To take everything down (including deleting the database) `make down` will do that for you.



**WIP**: to run the automated tests ...

## Manuals and documentation

### A note for grassroots organizations
If you are starting a deployment for a grassroots organization, you can apply for a free social-impact responder account [here](https://www.ushahidi.com/pricing/apply-for-free) after verifying that you meet the criteria.


### Platform User Manual

The official reference on how to use the Platform. Create surveys, configure data sources... it's all in there!
[Platform User Manual](https://docs.ushahidi.com/platform-user-manual/)

### Platform Developer Documentation

Key pointers on installing and developing on the Platform.

[Platform Developer Documentation](https://docs.ushahidi.com/platform-developer-documentation/)

## Credits

## Contributors ✨

Thanks goes to the wonderful people who [[Contribute](CONTRIBUTING.md)]! See the list of contributors at [all-contributors](docs/contributors-to-ushahidi.md)
This project follows the [all-contributors](https://github.com/all-contributors/all-contributors) specification. Contributions of any kind welcome!

## Useful Links
- [Code of Conduct](https://docs.ushahidi.com/platform-developer-documentation/code-of-conduct)
- [Download][download]
- [Installation guides][setup-guides]
- [Developer and User Support][support]
- [REST API docs][rest-api-docs]
- [Get Involved][getin]
- [Bug tracker][issues]
- [About Ushahidi][ushahidi]
- [Ushahidi Platform v2][ush2]
