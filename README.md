# warehouse-docker
Docker image of the indicia warehouse
## Info

This contains a blank indicia warehouse - like its just after installing.

The passwort for the user `admin` is set to `adminadmin`.

The image exposes two ports: `80` to access indicia `5432` to access the postgres database.

The database users are: `indicia_user` with the password `indicia_user_pass` and `indicia_report_user` with the password `indicia_report_user_pass`.

## Instructions
Build with:

`sudo docker build -t indicia-warehouse .`

Run with: 

`sudo docker run  -p 80:80 -p 5432 -t -i indicia-warehouse`
