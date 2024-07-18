<?php
  require("script.php");
?><!DOCTYPE html>
<html>
<head>
  <title>Area51 functions merge</title>
  <style>
    /* Tags */
    input[type="button"], button {
      vertical-align: top;
    }
    input[type="text"] {
      width: 80%; max-width: 400px;
    }
    textarea {
      width: 80%; max-width: 400px; min-height: 80px;
    }
	table {
	  border: 1px solid grey;
	}
	th, td {
	  border: 1px solid black;
	}
	a:link, a:hover, a:visited, a:active {
	  color: grey;
	}
    /* Class */
    .inputInsert {
      background: lightyellow;
    }
    .inputDisabled {
      background: lightgrey;
    }
    /* IDs */
    #latestDate {
      max-width: 200px;
    }
    #debug {
      border: 1px solid grey; width: 80%; max-width: 800px; min-height: 200px;
      font-family: monospace;
    }
  </style>
</head>
<body>

<h2>Area51 functions merge <?php print (DEBUG ? "[DEBUG MODE]" : ""); ?></h2>

  <label for="Area51">Area 51 page link:</label><br>
  <input type="text" id="Area51" name="Area51" class="inputInsert" value="https://wiki.mudlet.org/w/Area_51">
  <input id="btn-start" type="button" value="START">

<p>Just click the "Start" button and wait for function loading.</p>

<h2>Output</h2>

  <table id="tableMerge">
    <thead>
	  <th>Section</th>
	  <th>Function</th>
	  <th>PR</th>
	  <th><input type="checkbox" class="btn-all" value="1" /> Merge</th>
	  <th><input type="checkbox" class="btn-all" value="1" /> Delete</th>
	  <th>Insert</th>
	  <th>Note</th>
	  <th>Content</th>
	</thead>
    <tbody>
	</tbody>
  </table>
  <br>
  <input id="btn-merge" type="button" value="MERGE" disabled="disabled">
  <input id="btn-delete" type="button" value="DELETE" disabled="disabled">

<h2>Debug windows</h2>

<div id="debug">

</div>

<script src="js/jquery.min.js"></script>
<script>
	const GITHUB_URL = '<?php print GITHUB_URL; ?>';
	$(document).ready(function() {
		// allow to select/deselect all the function for merging
		$(".btn-all").click(function (e) {
			var index = $(this).parent().index() + 1;
			$("#tableMerge tbody tr td:nth-child(" + index + ")").find(':input').prop("checked", $(this).is(":checked"));
		});

		// retrive the area51 functions and try to match it with the official page
		$("#btn-start").click(function (e) {
			if ($("#Area51").val() == "") {
				alert('Insert a valid Area51 url');
				return;
			} else {
				$('#Area51, #btn-start').prop('disabled', true);
				Area51Functions();
			}
		});

		// do the real merge and overwrite the original page with new information
		$("#btn-merge").click(function (e) {
			var mergeable = [];
			$('.mergeable:checked').each(function(i){
				mergeable[i] = $(this).val();
			});
			if (mergeable.length == 0) {
				alert("No functions to merge");
			} else if (confirm("Do you really want to merge this functions?")) {
				$('#btn-merge, #btn-delete').prop('disabled', true);
				Area51Insert(mergeable);
			}
		});
		
		// do the real merge and overwrite the original page with new information
		$("#btn-delete").click(function (e) {
			var deletable = [];
			$('.deletable:checked').each(function(i){
				deletable[i] = $(this).val();
			});
			if (deletable.length == 0) {
				alert("No functions to delete");
			} else if (confirm("Do you really want to delete this functions?")) {
				$('#btn-merge, #btn-delete').prop('disabled', true);
				Area51Delete(deletable);
			}
		});		
	});

  /*
   * Request Wikimedia to get functions element
   */
	function Area51Functions() {
		makeRequest({action: "Area51", area51: $("#Area51").val()}, function (status, data) {
			switch (status) {
				case "beforeSend":
					writeOutput("Area51 functions started...");
				break;
				case "success":
					var trg;
					var kSections, vSections, kFunctions, vFunctions, arrFunctions;
					if (data['data']['status'] == "OK") {
						writeOutput("[INFO] functions found: populate table");
						// Populate the table with functions to merge
						trg = $("#tableMerge tbody");
						trg.empty();
						for (kSections in data['data']['table']) {
							vSections = data['data']['table'][kSections];
							for (kFunctions in vSections) {
								vFunctions = vSections[kFunctions];
								if (vFunctions["NAME"] != "STUBLAST") {
									// Add link to wiki
									if (vFunctions["LINK"] != "") {
										vFunctions["LINK"] = '<a href="' + vFunctions["LINK"] + '" target="_blank">' + vFunctions["NAME"] + '</a>';
									} else {
										vFunctions["LINK"] = vFunctions["NAME"];
									}
									if (vFunctions["WIKI_LINK"] != "") {
										vFunctions["WIKI_LINK"] = '<a href="' + vFunctions["WIKI_LINK"] + '" target="_blank">' + vFunctions["NOTE"] + '</a>';
									} else {
										vFunctions["WIKI_LINK"] = vFunctions["NOTE"];
									}
									// Add link to github
									vFunctions["PR"] = vFunctions["PR"].replace(/#(\d+)/, '<a href="' + GITHUB_URL + '$1" target="_blank">#$1</a>');

									// Print the table row
									trg.append('<tr>' +
										'<td>' + kSections + '</td>' + "\n" +
										'<td>' + vFunctions["LINK"] + '</td>' + "\n" +
										'<td>' + vFunctions["PR"] + '</td>' + "\n" +
										'<td>' + (vFunctions['MERGE'] >= 1 ? '<input type="checkbox" class="mergeable" name="merge[]" value="' + vFunctions["ID"] + '" />' : '') + '</td>' + "\n" +
										'<td>' + (vFunctions['MERGE'] >= 1 ? '<input type="checkbox" class="deletable" name="delete[]" value="' + vFunctions["ID"] + '" />' : '') + '</td>' + "\n" +
										'<td>' + vFunctions["OFFSET_INSERT"] + '</td>' + "\n" +
										'<td>' + vFunctions["WIKI_LINK"] + '</td>' + "\n" +
										'<td>' + <?php print (DEBUG ? 'vFunctions["CONTENT"]' : '""'); ?> + '</td>' + "\n" +
									'</tr>');
								}
							}
						}

						$('#btn-merge, #btn-delete').prop('disabled', false);
					} else {
						writeOutput("[ERROR] functions error [2]: " + data['data']['status']);
					}
				break;
				case "error":
					writeOutput("[ERROR] functions error [1]: " + data);
					$('#Area51, #btn-start').prop('disabled', false);
				break;
			};
		});
	}

	/*
   * Insert wikimedia for selected functions
   */
  	function Area51Insert(merge) {
		makeRequest({action: "Area51Insert", area51: $("#Area51").val(), merge51: merge}, function (status, data) {
			switch (status) {
				case "beforeSend":
					writeOutput("Area51 merge started...");
					writeOutput("[INFO] functions list: " + merge);
				break;
				case "success":
					var trg, id;
					if (data['data']['status'] == "OK") {
						// Highlight the merged functions rows
						writeOutput("[INFO] insert complete: verify table");
						for (id in data['data']['merged']) {
							trg = $("#tableMerge tbody").find('.mergeable[value="' + data['data']['merged'][id] + '"]');
							trg.parents('tr').css('background', 'lightgreen');
						}
					} else {
						writeOutput("[ERROR] insert error [2]: " + data['data']['status']);
					}
				break;
				case "error":
					writeOutput("[ERROR] insert error [1]: " + data);
				break;
			};
		});
	}
	/*
   * Delete wikimedia for selected functions
   */
  	function Area51Delete(del) {
		makeRequest({action: "Area51Delete", area51: $("#Area51").val(), delete51: del}, function (status, data) {
			switch (status) {
				case "beforeSend":
					writeOutput("Area51 delete started...");
					writeOutput("[INFO] functions list: " + del);
				break;
				case "success":
					var trg, id;
					if (data['data']['status'] == "OK") {
						// Highlight the merged functions rows
						writeOutput("[INFO] delete complete: verify table");
						for (id in data['data']['deleted']) {
							trg = $("#tableMerge tbody").find('.deletable[value="' + data['data']['deleted'][id] + '"]');
							trg.parents('tr').css('background', 'orange');
						}
					} else {
						writeOutput("[ERROR] delete error [2]: " + data['data']['status']);
					}
				break;
				case "error":
					writeOutput("[ERROR] delete error [1]: " + data);
				break;
			};
		});
	}	
  /*
   * Write to debug panel
   */
	function writeOutput(message, style) {
		var d = new Date();
		var h = d.getHours();
		var m = d.getMinutes();
		var s = d.getSeconds();
		$("#debug").append((h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ': ' + message + "<br>");
	}

  /*
   * Ajax request just a little better
   */
	function makeRequest(reqParam, callBack) {
		var req = {
			"param": reqParam
		};

		$.ajax({
			url: 'ajax.php',
			type: 'post',
			data: req,
			success: function (data) {
				var response, tmpHTML, elem, tmpObj, i;
				try {
					response = $.parseJSON(data);
				} catch (err) {
					_console(response);
				}
				switch (response['param']['type']) {
					default:
						if (callBack) callBack('success', response);
					break;
				}
			},
			beforeSend: function () {
				switch (req['param']['type']) {
					default:
						if (callBack) callBack('beforeSend');
					break;
				}
			},
			error: function (xhr, text) {
				switch (req['param']['type']) {
					default:
						if (callBack) callBack('error', text);
					break;
				}
			}
		});
	}
</script>
</body>
</html>