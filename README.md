# A PHP script that dumps all your MySQL databases' contents to a similarly named .sql file.

### IMPORTANT: This script was created based on Apache2 linux server. It has not been tested with other php based hosting methods. The development server is configured with PHP, Apache2 Server and MySQL with the default options. You must use a user that has all privilages in order for the script to work.

## I DO NOT KNOW IF IT WILL WORK ON OTHER ENVIRONMENTS, PLEASE USE WITH CAUTION!

Download and copy the PHP script inside your "/var/www/html". Then, go to your designated link (localhost if you are on the machine, else the server address). Example path to access the script through localhost: <code>localhost/dumper.php</code>

From there you will get a helper message explaining everything you need to do for the script to work properly.

Example of full script path and arguments: <br><code>url/dumper.php?user=[mysql username]&pass=[mysql password]&host=[mysql host]</code>

As of now, the folder and files are created in the document root folder (default: <code>/var/www/html</code>, the same as the script if you run Apache2). In the future, I will try to add a path option, though it might never happen, since PHP might not have access to the path of choice.

## Why this script?

This script was created for MySQL dumps that have incompatible data between MySQL versions, such as GeoPoints and other binary data. It makes sure to get those points with the AsText function, so it can be easily imported from any MySQL version. I developed this script when I was asked to import a MySQL 5.5.62 dump to a MySQL 8.0.21 server, hence the idea. Please note, this was developed from a machine I had rights and access to, with backups available. Anything that went wrong, I could reverse it. I suggest you do the same, so no data will get corrupted or lost. Make sure you have everything backed up and that any data loss or corruption from the script (if there is any) can be reverted.
