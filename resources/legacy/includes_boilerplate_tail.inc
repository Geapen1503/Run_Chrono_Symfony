// FIn function tab_param 	
//TABLEAU DE VALEUR
//FIN 1)



	
//2) On indique les numéros de dossards des listes déroulantes
//function start_time_course($id_html, $num_course)

$liste_des_courses='
<table style="color:green"; border=1>
<tr>
	<td> '.$id_html.'</td>
	<td>table RUN '.$tableau_inscription_param['table_run'].'<br />table DAG '.$tableau_inscription_param['table_dag_import'].'</td>
</tr>
</table>
<table style="color:blue"; border=1>
<tr>
	<td> Nom des courses</td>
	<!--<td> Value tab_param[nom_export_chronometrage]</td>-->
	<td> start_time_course</td>
	<!--<td> Value tab_param[nom_course]</td>-->
</tr>
	'
	.nom_export_chronometrage($id_html, -1)
	.nom_export_chronometrage($id_html, -2)
	.nom_export_chronometrage($id_html, -3)
	.nom_export_chronometrage($id_html, -4)
	.nom_export_chronometrage($id_html, -5)
	.nom_export_chronometrage($id_html, -6)
	.'

</table>

';

	$preparation_dossards=$liste_des_courses;
	
	function option_value()
	{
		$liste_deroulant = '';
		if (nom_course(-1) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-1)."\">".nom_course(-1)."</option>"  ;}
		if (nom_course(-2) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-2)."\">".nom_course(-2)."</option>"  ;}
		if (nom_course(-3) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-3)."\">".nom_course(-3)."</option>"  ;}
		if (nom_course(-4) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-4)."\">".nom_course(-4)."</option>"  ;}
		if (nom_course(-5) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-5)."\">".nom_course(-5)."</option>"  ;}
		if (nom_course(-6) <> 'COURSE_NON_CODE') { $liste_deroulant = $liste_deroulant."<option value=\"".nom_course(-6)."\">".nom_course(-6)."</option>"  ;}
	/*
	return  "<option value=\"".nom_course(-1)."\">".nom_course(-1)."</option>".					
			"<option value=\"".nom_course(-2)."\">".nom_course(-2)."</option>".								
			"<option value=\"".nom_course(-3)."\">".nom_course(-3)."</option>";
	*/
	return  $liste_deroulant;
	}	

		
	//Nom des épreuves 
	/*
	$course_1=nom_course(-1); 
	$course_2=nom_course(-2);
	$course_3=nom_course(-3);
	$course_4=''; //
	$course_5=''; //
	$course_6=''; //
	*/
	if (nom_course(-1) <> 'COURSE_NON_CODE') {$course_1=nom_course(-1);} else {$course_1='';}
	if (nom_course(-2) <> 'COURSE_NON_CODE') {$course_2=nom_course(-2);} else {$course_2='';}
	if (nom_course(-3) <> 'COURSE_NON_CODE') {$course_3=nom_course(-3);} else {$course_3='';}
	if (nom_course(-4) <> 'COURSE_NON_CODE') {$course_4=nom_course(-4);} else {$course_4='';}
	if (nom_course(-5) <> 'COURSE_NON_CODE') {$course_5=nom_course(-5);} else {$course_5='';}
	if (nom_course(-6) <> 'COURSE_NON_CODE') {$course_6=nom_course(-6);} else {$course_6='';}
//FIN 2)

//GESTION DES COULEURS
$course_1_couleur='#FFFFFF'; //'#FC5D5D';//bleu
$course_2_couleur='#FFFF6B';//jaune
$course_3_couleur='#FFFF6B';//rouge
$course_4_couleur='#FFFF6B';
$course_5_couleur='#FFFF6B';
$course_6_couleur='#FFFF6B';

	$evenement=$tableau_inscription_param['nom_evenement'];
	$evenement_date=$tableau_inscription_param['libelle_evenement_date'];

	//Nom des table RUN et DAG_IMPORT 
	$table_dag = $tableau_inscription_param['table_dag_import'];
	$table_run =  $tableau_inscription_param['table_run'];
	if (isset($tableau_inscription_param['table_depart_tspreel'])) {$table_depart_tspreel=$tableau_inscription_param['table_depart_tspreel'];};
	
	function le_dossard_existe($num_dossard){
		return tab_param($num_dossard)['le_dossard_existe'];
	}
			
	function distance_metre($num_dossard){
		return tab_param($num_dossard)['distance_metre'];
	}	

	function start_time_defaut($num_dossard){		
		 return tab_param($num_dossard)['start_time_defaut'];
	}

/*
//MAJ : on retire la notion d'export oninscrit.com 
	function nom_export_chronometrage($id_html, $num_dossard){		
		 if (tab_param($num_dossard)['le_dossard_existe'] <> 'DOSSARD_NON_CODE') {
			return '<tr><td>'.tab_param($num_dossard)['le_dossard_existe'] .'</td><td>'. tab_param($num_dossard)['nom_export_chronometrage'].'</td><td>'.start_time_course($id_html,abs($num_dossard)).'</td><td>'.tab_param($num_dossard)['nom_course'].'</td></tr>';
		 }
	}
*/
	function nom_export_chronometrage($id_html, $num_dossard){		
		 if (tab_param($num_dossard)['le_dossard_existe'] <> 'DOSSARD_NON_CODE') {
			return '<tr><td>'.tab_param($num_dossard)['le_dossard_existe'] .'</td><td>'.start_time_course($id_html,abs($num_dossard)).'</td></tr>';
		 }
	}

//---------------------------------------------------------
	function tab_abandon_course(){
		return array('999999','99999');
	}

function start_time_course($id_html, $num_course) {
//Cette fonction crée le 22/08/2022, renvoie l'heure de début de la course renseigné dans la table param_inscription_manuelle	
	if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
		//LOCALHOST: echo 'Le serveur tourne sous Windows !';
		$servername = "localhost";
		$host="localhost";
		$username = "root";
		$user="root"; 
		$password = "";
		$pass="";
		$database="runchrono-archive_race";
		$db="runchrono-archive_race";
	} else {
		//ALWAYSDATA: echo 'Le serveur ne tourne pas sous Windows !';
		$servername = "mysql-runchrono.alwaysdata.net";
		$host="mysql-runchrono.alwaysdata.net";
		$username = "runchrono-archive";
		$user="runchrono-archive"; 
		$password = "iletait1foisdansl@ued";
		$pass="iletait1foisdansl@ued";
		$database="runchrono-archive_race";
		$db="runchrono-archive_race";
	}
		$conn = mysqli_connect($servername, $username, $password,$db);
		//MAJ : 07/11/2020 : ajout PDO pour requete sql modernes 
		$db_PDO = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
		$db_PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$db_PDO->exec("set names utf8");

	$sql_heure_depart='SELECT heure_depart_course_'.trim($num_course).' as heure_depart FROM param_inscription_manuelle where id_html=\''.trim($id_html).'\'';
	//echo "<br /> sql_heure_depart = $sql_heure_depart <br />";
	$sql_result_heure = $db_PDO->query($sql_heure_depart);
	while ($row = $sql_result_heure->fetch(PDO::FETCH_ASSOC)) 
	{
		$result_heure = $row['heure_depart'];
	}	
	return $result_heure;			
}
//FIN Fonction start_time_course($num_course)

	function duree_de_course($num_dossard){
	$start_course_time=start_time_defaut($num_dossard);
	$libelle_course=nom_course($num_dossard);
	$h1=strtotime($start_course_time);
	$h2=strtotime("now");		
	$chrono_time=gmdate('H:i:s',$h2-$h1);
	if (intval(gmdate('H',$h2-$h1)) < 20 ) 
	{
		if (intval(gmdate('H',$h2-$h1)) < 1) 
		{
			// Si le chrono est inférieur à 1heure, on affiche pas l'heure
			$chrono_php = gmdate('i\m s\s',$h2-$h1);
		}
		else
		{
			$chrono_php = gmdate('H\h i\m s\s',$h2-$h1);
		}
		return htmlentities("Temps de course = $chrono_php");
		//echo "<br />start_course_time = $start_course_time - chrono_time = $chrono_time - chrono_php = $chrono_php<br />";
	}
	else
	{
		return htmlentities(" Course non d&eacute;marr&eacute;e ");
	}
	;	
		
	}	
//--------------------------------------------------------
//---EVOLUTION FONCTION nom_course qui devient nom_course_check_abandon
	function nom_course($num_dossard){
		return tab_param($num_dossard)['nom_course'];
	}	
	function fonc_nom_export_chronometrage($num_dossard){
		return tab_param($num_dossard)['nom_export_chronometrage'];
	}
	function nom_export_distance_onsincrit($num_dossard){
		return tab_param($num_dossard)['nom_export_distance_onsincrit'];
	}		
	function le_dossard_existe2($num_dossard){
		return tab_param($num_dossard)['le_dossard_existe'];
	}	
	function nom_course_check_abandon($num_dossard){
		// 3 sur 5		
		$abandon=0; foreach (tab_abandon_course() as  $tab_abandon_course_value) { if($num_dossard == $tab_abandon_course_value){ $abandon=1; }}
		/*------------DEBUT TRAITEMENT SQL - INTERROGATION DE LA TABLE ABANDON -----------------*/
				//PARAMETRES_DATABASE :
				/*
				$host3="localhost";
				$user3="root"; 
				$pass3="";
				$database3="runchrono-archive_race";
				$db3 = new PDO("mysql:host=$host3;dbname=$database3", $user3, $pass3);
				$db3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$db3->exec("set names utf8");
				*/
				//FIN PARAMETRES_DATABASE :
							
				if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
					//LOCALHOST: echo 'Le serveur tourne sous Windows !';
					$servername = "localhost";
					$host="localhost";
					$username = "root";
					$user="root"; 
					$password = "";
					$pass="";
					$database="runchrono-archive_race";
					$db="runchrono-archive_race";
				} else {
					//ALWAYSDATA: echo 'Le serveur ne tourne pas sous Windows !';
					$servername = "mysql-runchrono.alwaysdata.net";
					$host="mysql-runchrono.alwaysdata.net";
					$username = "runchrono-archive";
					$user="runchrono-archive"; 
					$password = "iletait1foisdansl@ued";
					$pass="iletait1foisdansl@ued";
					$database="runchrono-archive_race";
					$db="runchrono-archive_race";
				}

					$conn = mysqli_connect($servername, $username, $password,$db);
					//MAJ : 07/11/2020 : ajout PDO pour requete sql modernes 
					$db_PDO = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
					$db_PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					$db_PDO->exec("set names utf8");
		
		
		$sql_count_distinct='SELECT count(DISTINCT num_dossard) FROM abandon where num_dossard=\''.$num_dossard.'\'';
		//echo "<br /> sql_count_distinct = $sql_count_distinct";
		$result_distinct = $db_PDO->query($sql_count_distinct);
		while ($row = $result_distinct->fetch(PDO::FETCH_ASSOC)) 
		{
			$count_distinct = $row['count(DISTINCT num_dossard)'];
			if ($count_distinct > 0)
				{
					$abandon = 1;
				}			
		}	
					
		/*------------FIN TRAITEMENT SQL - INTERROGATION DE LA TABLE ABANDON -----------------*/
		if ($abandon == 1) { return 'ABANDON_COURSE';}
		
		return tab_param($num_dossard)['nom_course'];
	}	
//---------------------------- 	FIN FONCTIONS QUI POINTENT SUR LES TABLEAUX DE PARAMETRES : ON NE TOUCHE à RIEN---------------------------------------------




?>
