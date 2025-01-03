<?php
  require("script.php");
?><!DOCTYPE html>
<html>
<head>
  <title>Area51 functions merge</title>
  <style>
    /* Tags */
	body {
	  font-size: 14px;
	}
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
	  padding: 2px 5px;
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
    .mergeOK {
      color: green;
    }
    .mergeKO {
      color: red;
	  font-size: 0.8em;
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
  <input type="text" id="Area51" name="Area51" class="inputInsert" value="https://wiki.mudlet.org/w/Area_51"><br>
  <label for="WikiBotUser">Wiki Bot User:</label><br>
  <input type="text" id="WikiBotUser" name="WikiBotUser" class="inputInsert" value="<?php print WIKI_BOT_USER; ?>"><br>
  <label for="WikiBotPass">Wiki Bot Pass:</label><br>
  <input type="text" id="WikiBotPass" name="WikiBotPass" class="inputInsert" value="<?php print WIKI_BOT_PASS; ?>"><br>
  <input id="btn-start" type="button" value="START / RESET">

<p>Just click the "Start / Reset" button and wait for function loading.</p>
<p>NOTE: Credential generated at https://wiki.mudlet.org/w/Special:BotPasswords, needed only "Edit pages" permission</p>

<h2>Output</h2>

  <table id="tableMerge">
    <thead>
	  <th>Section</th>
	  <th>Function</th>
	  <th>PR (merged)</th>
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
  <input id="btn-refresh" type="button" value="REFRESH" disabled="disabled">

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
		$("#btn-start, #btn-refresh").click(function (e) {
			if ($("#Area51").val() == "") {
				alert('Insert a valid Area51 url');
				return;
			} else {
				$('#Area51, #btn-start').prop('disabled', true);
				$('#btn-merge, #btn-delete, #btn-refresh').prop('disabled', true);

				var mergeable = [];
				var deletable = [];
				// salvo campi merge/delete solo in caso di refresh
				if ($(this).attr('id') == 'btn-refresh') {
					$('.mergeable:checked').each(function(i){
						mergeable[i] = $(this).val();
					});
					
					$('.deletable:checked').each(function(i){
						deletable[i] = $(this).val();
					});				
				}
				Area51Functions(mergeable, deletable);
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
			} else {
				if ($("#WikiBotUser").val() == "" || $("#WikiBotPass").val() == "") {
					alert("Wiki Bot User / Pass are mandatory for merging!");
				} else if (confirm("Do you really want to merge this functions?")) {
					$('#btn-merge, #btn-delete, #btn-refresh').prop('disabled', true);
					Area51Insert(mergeable);
				}
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
			} else { 
				if ($("#WikiBotUser").val() == "" || $("#WikiBotPass").val() == "") {
					alert("Wiki Bot User / Pass are mandatory for deleting!");
				} else if (confirm("Do you really want to delete this functions from Area51?")) {
					$('#btn-merge, #btn-delete, #btn-refresh').prop('disabled', true);
					Area51Delete(deletable);
				}
			}
		});
	});

  /*
   * Request Wikimedia to get functions element
   */
	function Area51Functions(mergeable, deletable) {
		makeRequest({
            WikiBotUser: $("#WikiBotUser").val(), WikiBotPass: $("#WikiBotPass").val(),
            action: "Area51", area51: $("#Area51").val(),
			merge51: mergeable, delete51: deletable
        }, function (status, data) {
            switch (status) {
                case "beforeSend":
					$("#tableMerge tbody").empty();
                    writeOutput("Area51 functions started...");
                break;
                case "success":
                    var trg;
                    var kSections, vSections, kFunctions, vFunctions, arrFunctions;
                    if (data['data']['status'] == "OK") {
                        writeOutput("[INFO] functions found: populate table");
                        // Populate the table with functions to merge
                        trg = $("#tableMerge tbody");
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

						// Reload active check for merge/delete
						for (id in data['param']['merge51']) {
							$("#tableMerge tbody").find('.mergeable[value="' + data['param']['merge51'][id] + '"]').prop('checked', true);
						}
						for (id in data['param']['delete51']) {
							$("#tableMerge tbody").find('.deletable[value="' + data['param']['delete51'][id] + '"]').prop('checked', true);
						}						

                        $('#btn-merge, #btn-delete, #btn-refresh').prop('disabled', false);
                    } else {
                        writeOutput("[ERROR] functions error [2]: " + data['data']['status']);
                    }
					$('#Area51, #btn-start').prop('disabled', false);
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
		makeRequest({
            WikiBotUser: $("#WikiBotUser").val(), WikiBotPass: $("#WikiBotPass").val(),
            action: "Area51Insert", area51: $("#Area51").val(), merge51: merge
        }, function (status, data) {
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
					$('#btn-refresh').prop('disabled', false);
				break;
				case "error":
					writeOutput("[ERROR] insert error [1]: " + data);
					$('#btn-refresh').prop('disabled', false);
				break;
			};
		});
	}
  /*
   * Delete wikimedia for selected functions
   */
  	function Area51Delete(del) {
		makeRequest({
            WikiBotUser: $("#WikiBotUser").val(), WikiBotPass: $("#WikiBotPass").val(),
            action: "Area51Delete", area51: $("#Area51").val(), delete51: del
        }, function (status, data) {
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
					$('#btn-refresh').prop('disabled', false);
				break;
				case "error":
					writeOutput("[ERROR] delete error [1]: " + data);
					$('#btn-refresh').prop('disabled', false);
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