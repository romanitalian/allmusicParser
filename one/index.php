<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>by one artist</title>
	<script src="js/jquery-1.11.3.min.js"></script>
	<script src="js/moment-develop/moment.js"></script>
	<script src="js/moment-develop/locale/ru.js"></script>
</head>
<body>
<div id="block">start:</div>
<script type="text/javascript">
$(document).ready(function(){
	var dateTimeStart = moment().format('YYYY-MM-DD HH:mm:ss');
	var block = $('#block');
	var content = '';
	var saveArtist = function(artist) {
		return $.ajax({
			url: "main.php",
			data: 'artist=' + artist
		})
		.done(function(e) {
			// console.log('done');
			content = block.html();
			block.html(content + '<br />' + e + '<br />');
		})
		.complete(function() {
			getNewArtistAndSaveIt();
		});
	};
	function getNewArtistAndSaveIt() {
		$.ajax({
			url: "getartist.php"
		})
		.done(function(data) {
			var artistObj = JSON.parse(data);
			if(!!(artistObj.for_url && artistObj.origin)) {
				var res = saveArtist(artistObj.for_url);
				content = block.html();
				block.html(content + '<br />' + artistObj.origin);
				return true;
			} else {
				content = block.html();
				block.html(content + '<br />' + '== no new artist ==<br />*<br />');
				return;
			}
		});
	}
	(function run() {
		// return ;
		block.html(block.html() + ' ' + dateTimeStart);
		getNewArtistAndSaveIt();
	}());
});
</script>
</body>
</html>