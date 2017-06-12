# warehouse-docker
Docker image of the indicia warehouse
## Info

This contains a blank indicia warehouse - like its just after installing.

The passwort for the user `admin` is set to `adminadmin`.

## Instructions
Build with:

`sudo docker build -t indicia-warehouse .`

Run with: 

`sudo docker run  -p 80:80 -p 5432 -t -i indicia-warehouse`
