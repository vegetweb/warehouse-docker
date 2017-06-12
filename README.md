# warehouse-docker
Docker image of the indicia warehouse

## Instructions
Build with:

`sudo docker build -t indicia-warehouse .`

Run with: 

`sudo docker run  -p 80:80 -p 5432 -t -i indicia-warehouse`
