## About
Approach Zero search relay PHP server.

Set environment variable `A0_QRYLOGD` or `A0_SEARCHD` to target services if they are not on `localhost`:
```
# docker run -it -p 8080:8080 -e A0_SEARCHD=1.2.3.4 a0-relay
```

`A0_SEARCHD` should be listening on port 8921 and `A0_QRYLOGD` should be listening on port 3207.

The query object
```php
$query_obj = array(
	"ip" => $remote_ip,
	"page" => $req_page,
	"kw" => array(
		array("type" => $kw_type, "str" => $kw_str),
		...
	)
);
```
will be sent in JSON to both target services.

### Local test
```
# docker network create --driver=overlay --attachable test_net
# docker run -it --name a0 --network test_net -v /path/to/your/host/index:/mnt/index approach0/a0 searchd.out -i /mnt/index -c0 -C0
# docker run -it --network test_net -e A0_SEARCHD=a0 -p 8080:8080 a0-relay
```
then visit `http://localhost:8080/search-relay.php?q=test`.

Alternatively, test a0-relay in host network directly:
```
# docker run -it --network host a0-relay
```
