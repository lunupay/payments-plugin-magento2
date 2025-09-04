# Installing Lunu Widget in Magento 2.x

Copy the folder ``` app/code/Lunu ``` to the site directory ``` app/code/ ```


Then execute the commands in the root of the site:
```sh
php bin/magento module:enable Lunu_Merchant
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
```

## Plugin Configuration

Enable and configure Lunu plugin in Magento Admin under `Stores / Configuration / Sales / Payment Methods / Lunu Pay`.



# Attention!

Keep in mind that if the site in which You are testing payments is not publicly
accessible for requests from the Internet, then notifications of changes in
the status of payments from Our processing service will not be able to reach
Your online store, as a result of which the status of orders in your store will not change.




## API credentials

You can get your credentials in your account on the console.lunu.io website
in the section https://console.lunu.io/developer-options  


For debugging, you can use the following credentials:  

  - sandbox mode:
    - App Id: 8ce43c7a-2143-467c-b8b5-fa748c598ddd
    - API Secret: f1819284-031e-42ad-8832-87c0f1145696

  - production mode:
    - App Id: a63127be-6440-9ecd-8baf-c7d08e379dab
    - API Secret: 25615105-7be2-4c25-9b4b-2f50e86e2311
