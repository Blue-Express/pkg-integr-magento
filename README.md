# Bx Magento

## Instalacin

Para concretar la instalacion del mdulo es necesario realizar los siguientes pasos. Primeramente, parados en la carpeta root del proyecto:

 Ejecute los siguientes comandos de magento:
```
	composer require blue/express:dev-main
	
	php bin/magento setup:upgrade
        php bin/magento setup:di:compile
	php bin/magento cache:clean
```
