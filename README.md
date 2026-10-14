<div align="center">

<img src="view/adminhtml/web/images/dpd-logo-transparant.png" alt="DPD Connect" width="160" />

# DPD Connect for Magento 2

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![Magento 2.3+](https://img.shields.io/badge/Magento-2.3%2B-EE672F?logo=magento&logoColor=white)
![License GPL-3.0](https://img.shields.io/badge/License-GPL--3.0-blue)
![Version 1.0.8](https://img.shields.io/badge/Version-1.0.8-brightgreen)

**Integrate DPD parcel shipping directly into your Magento 2 store.**
Generate labels, offer Parcelshop pickup at checkout, and monitor async batches — all from the Magento admin.

[Features](#-features) · [Requirements](#-requirements) · [Installation](#-installation) · [Configuration](#-configuration) · [Development](#-development)]
</div>

---

## 📦 Features

### 🏷️ Label Generation

| Feature | Description |
|---|---|
| **Single label** | Generate from any order page via the order actions menu |
| **Bulk labels** | Select multiple orders from the sales grid and process in one action |
| **Return labels** | Generated and stored separately from shipping labels |
| **Fresh & Freeze** | Temperature-controlled shipments with per-parcel expiration dates |
| **Multi-parcel** | Split a single order across multiple parcels via the packaging popup |

### 🗺️ Delivery Options

| Feature | Description |
|---|---|
| **DPD Classic** | Standard delivery via the unified DPD carrier with customer product selection |
| **DPD Parcelshop** | Embedded Google Maps picker at checkout — customer chooses a pickup point |
| **DPD Predict** | Home delivery with email notification for B2C and B2B MSG products |
| **Saturday delivery** | Method automatically hidden outside a configurable day + time window |
| **Express (E10 / E12)** | Guaranteed delivery before 10:00 or 12:00 |
| **Guarantee 18** | Guaranteed delivery before 18:00 |
| **Age check** | Flag products to require recipient age verification at delivery |

### ⚡ Processing & Downloads

| Feature | Description |
|---|---|
| **Synchronous** | Small batches generated and downloaded immediately as PDF |
| **Asynchronous** | Larger batches queued at DPD; labels delivered via webhook callback |
| **A4 and A6** | Choose the format that matches your printer |
| **ZIP or merged PDF** | Download bulk labels as a ZIP archive or a single merged PDF |
| **Shipping list** | Printable pick list for warehouse staff |

### 🔧 Admin & Integration

| Feature | Description |
|---|---|
| **Batch monitor** | Sales → DPD → Batches grid shows status of every async job |
| **Label archive** | Sales → DPD → Labels grid with download and mass-download actions |
| **Table rates** | Per-carrier custom rate tables (by weight, price, or qty) |
| **Customs support** | HS code, export description, and consignee data sent for international shipments |
| **Multi-store** | All credentials and settings configurable per website / store view |
| **REST API** | `dpd_parcelshop_id` exposed on Magento Order API via extension attributes |

---

## ✅ Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.1 |
| Magento Framework | ≥ 103.0.6 (Magento 2.3+) |
| `dpdconnect/php-sdk` | ^2.1 |
| Google Maps API key | Required for Parcelshop pickup |

---

## 🚀 Installation

```bash
composer require dpdconnect/magento2-shipping
```

After installation run the standard Magento upgrade sequence:

```bash
php bin/magento module:enable DpdConnect_Shipping
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

### Upgrade

```bash
composer update dpdconnect/magento2-shipping
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

---

## ⚙️ Configuration

Navigate to **Stores → Configuration → Sales → DPD Parcelservice**.

### Account Settings

| Setting | Description |
|---|---|
| **Username** | Your DPD Connect API username |
| **Password** | Your DPD Connect API password (stored encrypted) |
| **Depot** | Your DPD depot number |
| **Print format** | Label paper size — A4 or A6 |
| **Test Connection** | Button to verify credentials against the DPD API |

### Carrier Settings

Navigate to **Stores → Configuration → Sales → Shipping Methods**.
Each DPD carrier (`DPD`, `DPD Pickup`, `DPD Predict`, `DPD Saturday`, etc.) has its own section with price, rate type, and allowed countries.

### Advanced

| Setting | Description |
|---|---|
| **Include return label** | Generate a return label together with every shipping label |
| **Email return label** | Email the return label PDF to the customer |
| **Picqer mode** | Store parcelshop name in the company field instead of the customer name |
| **Save labels as file** | Write label PDFs to disk (default: stored in the database) |
| **Age check** | Enable per-product age verification at delivery |
| **Async threshold** | Number of orders above which label generation switches to async mode |

---

## 🛠️ Development

### Module structure

```
DpdConnect_Shipping/
├── Api/                    # Interfaces (ApiCallbackInterface)
├── Block/                  # Admin and frontend blocks
├── Config/Source/          # Dropdown source models
├── Controller/             # Admin + frontend controllers
├── etc/                    # XML configuration (di, events, webapi, system)
├── Helper/                 # DPDClient, DpdSettings, Data, OrderConvertService
├── Model/                  # Carriers, ResourceModels, CheckoutConfigProvider
├── Observer/               # Order, shipment and config event observers
├── Plugin/                 # Order API extension attribute plugins
├── Services/               # AuthenticationService, BatchManager, ShipmentManager
├── Setup/                  # UpgradeSchema, UpgradeData
├── Ui/                     # UI component providers
├── ViewModel/              # CheckShipment view model
├── i18n/                   # Translations (EN, NL, DE, ES, FR, IT, PL)
└── view/                   # Layout XML, templates, JS, CSS
```

### Useful commands

```bash
# Flush cache during development
php bin/magento cache:flush

# Recompile dependency injection
php bin/magento setup:di:compile

# Reindex
php bin/magento indexer:reindex

# Run PHP linter
vendor/bin/phplint .
```

### Webhook endpoint

DPD calls back on this endpoint after async label generation:

```
POST /rest/V1/dpd-shipping/callback
```

No Magento authentication is required (`<resource ref="anonymous"/>`). The callback payload contains the job ID, parcel numbers, label data (fetched separately), and error information.

---

## ❓ FAQ

**I get a "Bad credentials" error when printing a label.**

> If you use `env.php` to specify credentials, use `bin/magento config:sensitive:set` to set the password — it must be stored encrypted.
> ```bash
> bin/magento config:sensitive:set dpdshipping/account_settings/password "yourpassword"
> ```

**DPD Fresh / Freeze orders fail when creating a shipment.**

> Fresh and Freeze products can only be shipped through the DPD order overview or the packages screen. Standard Magento shipment creation is blocked by design to ensure expiration dates and product descriptions are filled in correctly.

**Labels are not appearing after async batch processing.**

> Check **Sales → DPD → Batches** for the job status. If jobs show `failed`, verify the webhook URL is publicly accessible:
> `https://your-store.com/rest/default/V1/dpd-shipping/callback`

**Saturday delivery is not showing at checkout.**

> The Saturday carrier has a configurable display window. Go to **Stores → Configuration → Sales → Shipping Methods → DPD Saturday** and check the "Shown from" and "Shown till" day/time settings.


