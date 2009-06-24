<?php

	function get_status($num){
		if ( $num == 0 ) {
			return "Not started yet.";
		} else if ( $num == 1 ) {
			return "In progress.";
		} else {
			return "Completed.";
		}
	}

	function get_status2($num, $fail){
		if ( $num == 0 ) {
			return "notstarted notdone";
		} else if ( $num == 1 ) {
			return "progress notdone";
		} else {
			return $fail > 0 ? "fail" : "pass";
		}
	}

	$job_id = ereg_replace("[^0-9]", "", $_REQUEST['job_id']);

	$result = mysql_queryf("SELECT name, status FROM jobs WHERE id=%u;", $job_id);

	if ( $row = mysql_fetch_array($result) ) {
		$job_name = $row[0];
		$job_status = get_status(intval($row[1]));
	}

?>

<h3><?=$job_name?></h3>

<table class="results"><tbody>
<?php

	$result = mysql_queryf("SELECT runs.id as run_id, runs.url as run_url, runs.name as run_name, useragents.engine as browser, useragents.name as browsername, useragents.os as os, useragents.id as useragent_id, run_useragent.status as status FROM run_useragent, runs, useragents, jobs WHERE jobs.id=%u AND runs.job_id=jobs.id AND run_useragent.run_id=runs.id AND run_useragent.useragent_id=useragents.id ORDER BY run_id, browsername;", $job_id);

	$last = "";
	$output = "";
	$browsers = array();
	$addBrowser = true;

	while ( $row = mysql_fetch_assoc($result) ) {
		if ( $row["run_id"] != $last ) {
			if ( $last ) {
				if ( $addBrowser ) {
					$header = "<tr><th></th>\n";
					$last_browser = array();
					foreach ( $browsers as $browser ) {
						if ( $last_browser["id"] != $browser["id"] ) {
							$header .= '<th><div class="browser">' .
								'<img src="/images/' . $browser["engine"] .
								'.sm.png" class="browser-icon ' . $browser["engine"] .
								'" alt="' . $browser["name"] . '" title="' . $browser["name"] .
								'"/><span class="browser-name">' .
								preg_replace('/\w+ /', "", $browser["name"]) . ', ' .
								$browser["os"] . '</span></div></th>';
						}
						$last_browser = $browser;
					}
					$header .= "</tr>\n";
					$output = $header . $output;
				}

				$output .= "</tr>\n";
				$addBrowser = false;
			}

			$useragents = array();

			$runResult = mysql_queryf("SELECT run_client.client_id as client_id, run_client.status as status, run_client.fail as fail, run_client.total as total, clients.useragent_id as useragent_id, users.name as name, useragents.name as browser FROM useragents, run_client, clients, users WHERE run_client.run_id=%u AND run_client.client_id=clients.id AND clients.user_id=users.id AND useragents.id=useragent_id ORDER BY browser;", $row["run_id"]);

			while ( $ua_row = mysql_fetch_assoc($runResult) ) {
				if ( !$useragents[ $ua_row['useragent_id'] ] ) {
					$useragents[ $ua_row['useragent_id'] ] = array();
				}

				array_push( $useragents[ $ua_row['useragent_id'] ], $ua_row );
			}

			$output .= '<tr><th><a href="' . $row["run_url"] . '">' . $row["run_name"] . "</a></th>\n";
		}

		if ( $addBrowser ) {
			array_push( $browsers, array(
				"name" => $row["browsername"],
				"engine" => $row["browser"],
				"os" => $row["os"],
				"id" => $row["useragent_id"]
			) );
		}

		#echo "<li>" . $row["browser"] . " (" . get_status(intval($row["status"])) . ")<ul>";

		$last_browser = "";

		if ( $useragents[ $row["useragent_id"] ] ) {
			foreach ( $useragents[ $row["useragent_id"] ] as $ua ) {
				$status = get_status2(intval($ua["status"]), intval($ua["fail"]));
				if ( $last_browser != $ua["browser"] ) {
					$output .= "<td class='$status " . $row["browser"] . "'><a href='/?state=runresults&run_id=" . $row["run_id"] . "&client_id=" . $ua["client_id"] . "'>" . ($ua["status"] == 2 ? $ua["fail"] > 0 ? $ua["fail"] . " test(s) failed." : "Pass" : "&nbsp;") . "</a></td>\n";
				}
				$last_browser = $ua["browser"];
			}
		} else {
			$output .= "<td class='notstarted notdone'>&nbsp;</td>";
		}

		#echo "</ul></li>";

		$last = $row["run_id"];
	}

	echo "$output</tr>\n</tbody>\n</table>";
?>
<script>
setTimeout(function(){
	if ( jQuery("td.notdone").length ) {
		jQuery("table.results").load("/?state=jobstatus&job_id=<?= $job_id ?> tbody");
		setTimeout(arguments.callee, 3000);
	}
}, 3000);
</script>
