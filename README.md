Installation
------------

```bash
composer require dpdconnect/magento2-shipping
```

Upgrade
-------

```bash
composer update dpdconnect/magento2-shipping
```

After upgrade or install
------------------------

```bash
php bin/magento module:enable DpdConnect_Shipping
```
```bash
php bin/magento setup:upgrade
```
```bash
php bin/magento setup:di:compile
```
```bash
php bin/magento setup:static-content:deploy
```

Magento 2 Shipping module by DPDC


FAQ
--

* I get a "Bad credentials" error when printing a label
> If you use the env.php to specify the username and password make sure to use `bin/magento config:sensitive:set` to specify the password.
> The password has be encrypted in the env.php as well.