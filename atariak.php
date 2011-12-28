<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="eu" lang="eu">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 
<title>b5m2osm</title>
</head>
<body>
<?php
	if (isset($_POST['submit'])) { // page submitted
			
		$useCURL = isset($_POST['usecurl']) ? $_POST['usecurl'] : '0';
		setlocale(LC_CTYPE, 'es');
   		date_default_timezone_set('Europe/Madrid');
		$client = new SoapClient(dirname(__FILE__)."/b5m_atariak.wsdl");

	/*
	Nombre 	Tipo 	Valores de ej.
	Tipo 	String 	Los distintos tipos de B�squeda de la tabla pudiendo ser alguno de los siguientes: Muni, Calle, Numero, Edificio, CP, Distrito o Seccion
	lengua 	String 	
	    * 0.Castellano
	    * 1.Euskera
	    * 2.Inglés
	    * 3.Francés

	Muni 	String 	Nombre del Municipio, por ejemplo: donostia
	codmuni 	String 	Cédigo Eustat del Municipio, por ejemplo: 004
	Calle 	String 	Nombre de la calle, por ejemplo: aldakoenea
	codcalle 	String 	Código Eustat de la Calle, por ejemplo:1200
	Numero 	String 	Número de portal, por ejemplo: 010
	bis 	String 	Bis, por ejemplo: A o vacia
	nomedif 	String 	Es el nombre del edificio, por ejemplo: etxeberria
	codpostal 	String 	Código Postal: 001
	Distrito 	String 	Distrito: 04
	Sección 	String 	Sección: 002
	
	*/
		$param = array(		
			'lengua' => '1',			
			'tipo' => 'Numero',
			'muni' => '',
			'codmuni' =>$_POST["udalerria"],
			'calle' => $_POST["kalea"],
			'codcalle' => '',
			'numero' => '',
			'bis' => '',
			'nomedif' => '',
			'codpostal' =>'',
//			'codpostal' => $_POST["pk"],
			'distrito' => '',											
			'devtag' => ''
		);

		$result = $client->buscarcallejero($param);	

			if (is_soap_fault($result)) {
				trigger_error("SOAP Fault: (faultcode: {$result->faultcode}, faultstring: {$result->faultstring})", E_USER_ERROR);
			} else {

				$upload_directory="tmp";
				$parent_dir = array_pop(explode(DIRECTORY_SEPARATOR, dirname(__FILE__)));
				$upload_directory = substr(dirname(__FILE__), 0, strlen(dirname(__FILE__)) - strlen($parent_dir) ) . $upload_directory ; 
				$upload_directory = substr(dirname(__FILE__), 0, strlen(dirname(__FILE__)) - strlen($parent_dir) ) . $upload_directory ; 
				$md=md5(microtime());						
				$filename = dirname(__FILE__).'/tmp/'.$md.'b5m_export.osm'; 
				$filename_error = dirname(__FILE__).'/tmp/'.$md.'b5m_export_error.osm'; 
				$filename_data = dirname(__FILE__).'/tmp/'.$md.'b5m_export_data.osm'; 			
								
				$r=$result->result->objectOut;
				$n=count($r);
				
				if (!$handle = fopen($filename, 'w')) {
					echo "Cannot open ($filename)";
					exit;
				}	
				if (!$handle_error = fopen($filename_error, 'w')) {
					echo "Cannot open ($filename_error)";
					exit;
				}	
								
				$xml_output = "<?xml version='1.0' encoding='UTF-8'?>\n";
				$xml_output .= "<osm version='0.6' generator='JOSM'>\n";
				if (fwrite($handle, $xml_output) === FALSE) {
					echo "Cannot write to ($filename)";
					exit;
				}	
				if (fwrite($handle_error, $xml_output) === FALSE) {
					echo "Cannot write to ($filename_error)";
					exit;
				}	
										
				$id=-1;
				$ll=array();
				for($i=0;$i<$n;$i++){
					$p=$r[$i];
					$c=array($p->latWgs84,$p->lonWgs84,$p->calle,$p->numero);
					$zbkia="";					
					$zbkia=(int)$p->numero;				
					$xml_node = "  <node id='". $id ."' visible='true' lat='".$p->latWgs84."' lon='".$p->lonWgs84."'>\n";
					$xml_node .= "    <tag k='addr:postcode' v='".$p->codpostal."' />\n";
					$xml_node .= "    <tag k='addr:country' v='ES' />\n";
					$xml_node .= "    <tag k='addr:street' v='".ucwords(mb_strtolower($p->calle,"UTF-8"))."' />\n";
					$xml_node .= "    <tag k='addr:city' v='".ucwords(mb_strtolower($p->muni,"UTF-8"))."' />\n";
					if (isset($p->bis)&&($p->bis!=' ')&&($p->bis!='')){
						$zbkia.=$p->bis;
					}
					$xml_node .= "    <tag k='addr:housenumber' v='".$zbkia."' />\n";
					
					$eraikina=$_POST["eraikina"];
					if (($eraikina=="b")&&($p->nomedificio!=' ')&&($p->nomedificio!='')){
						$xml_node .= "    <tag k='name' v='".ucwords(mb_strtolower($p->nomedificio,"UTF-8"))."' />\n";
					}
					$aux_id="";
					$aux=strstr($p->urlOrto,"id");
					$b=explode("&",str_replace("=","&",$aux));
					$aux_id=$b[(array_search('id', $b)+1)];
					$xml_node .= "    <tag k='source' v='b5m' />\n";					
					$xml_node .= "    <tag k='b5m:id' v='".$aux_id."' />\n";	
					$xml_node .= "    <tag k='b5m:url' v='http://b5m.gipuzkoa.net/b5map/r1/eu/mapa/lekutu/".$aux_id."' />\n";	
					$aux=str_replace("&","&amp;",$p->urlOrto);
					$xml_node .= "    <tag k='b5m:urlOrto' v='".$aux."' />\n";	
					
				
										
					$xml_node .= "  </node>\n";	
					
					if (($zbkia==999)||($zbkia=="")||($p->codpostal=='')||($p->codpostal==' ')||(in_array($c, $ll))){
					//if (($zbkia==999)||($p["codpostal"]=='')||($p["codpostal"]==' ')){
						if (fwrite($handle_error, $xml_node) === FALSE) {
							echo "Cannot write to ($filename_error)";
							exit;
						}						
					} else {
						if (fwrite($handle, $xml_node) === FALSE) {
							echo "Cannot write to ($filename)";
							exit;
						}						
					}
					$id=$id-1;
					$ll[$i]=$c;			
				}
				$xml_output = "</osm>";
				if (fwrite($handle, $xml_output) === FALSE) {
					echo "Cannot write to ($filename)";
					exit;
				}	
				if (fwrite($handle_error, $xml_output) === FALSE) {
					echo "Cannot write to ($filename_error)";
					exit;
				}				
				fclose($handle);
				fclose($handle_error);
				?>			
				<p>Exportatutako datuak:<br/>
				<a href="<?php echo 'tmp/'.$md.'b5m_export.osm';?>">OSM fitxategia</a><br/>
				<a href="<?php echo 'tmp/'.$md.'b5m_export_error.osm';?>">OSM fitxategia exportazioan aurkitu diren akatsekin</a>	
				</p>
				<p>Atarien inportazioa:</p>
				<ol>
				<li>Mapeatu behar den azalera OSMtik kargatu</li>	
				<li>Fitxategiak gorde eta JOSMn ireki</li>
				<li>Validator plugina erabiliz puntu berdinetan dauden elementuak zuzendu(Warning moduan agertzen dira)</li>
				<li>Akatsen fitxategian dauden elementuak aztertu.(Bikoiztutako atariak eta zenbaki gabeko atariak agertuko dira) 
				<li>B5Mko daten geruza aktibatu eta OSMra datuak kargatu</li>
				</ol>
				<p>Eraikinen izenak etiketatzeko:</p>
				<ul>
				<li>Mapeatu behar den azalera OSMtik kargatu</li>	
				<li>Fitxategiak gorde eta JOSMn ireki</li>
				<li>Eraikinei "name" etiketak gehitu</li>	
				</ul>
				
				<p>Oharra:Datuak kontrastatu beharko dira.</p> 
				<?php	
			}
//		}
	} else {

		?>	
			<form method="post" action="<?php echo $PHP_SELF;?>">
				<input type="hidden" name="t" value="<?php echo microtime();?>">
		<?php	
		/*	
				Posta kodea:<input type="text" size="12" maxlength="12" name="pk"><br />
		*/
		?>
				Kalea:<input type="text" size="12" maxlength="36" name="kalea"><br />
				Udalerria:<br />
				<select name="udalerria">
				<option value="">Udalerria aukeratu</option>					
				<option value="001">Abaltzisketa</option>
				<option value="002">Aduna</option>
				<option value="016">Aia</option>
				<option value="003">Aizarnazabal</option>
				<option value="004">Albiztur</option>
				<option value="005">Alegia</option>
				<option value="006">Alkiza</option>
				<option value="906">Altzaga</option>
				<option value="007">Altzo</option>
				<option value="008">Amezketa</option>
				<option value="009">Andoain</option>
				<option value="010">Anoeta</option>
				<option value="011">Antzuola</option>
				<option value="012">Arama</option>
				<option value="013">Aretxabaleta</option>
				<option value="055">Arrasate/Mondragón</option>
				<option value="014">Asteasu</option>
				<option value="903">Astigarraga</option>
				<option value="015">Ataun</option>
				<option value="017">Azkoitia</option>
				<option value="018">Azpeitia</option>
				<option value="904">Baliarrain</option>
				<option value="019">Beasain</option>
				<option value="020">Beizama</option>
				<option value="021">Belauntza</option>
				<option value="022">Berastegi</option>
				<option value="074">Bergara</option>
				<option value="023">Berrobi</option>
				<option value="024">Bidegoian</option>
				<option value="029">Deba</option>
				<option value="069">Donostia-San Sebastián</option>
				<option value="030">Eibar</option>
				<option value="031">Elduain</option>
				<option value="033">Elgeta</option>
				<option value="032">Elgoibar</option>
				<option value="067">Errenteria</option>
				<option value="066">Errezil</option>
				<option value="034">Eskoriatza</option>
				<option value="035">Ezkio-Itsaso</option>
				<option value="038">Gabiria</option>
				<option value="037">Gaintza</option>
				<option value="907">Gaztelu</option>
				<option value="039">Getaria</option>
				<option value="040">Hernani</option>
				<option value="041">Hernialde</option>
				<option value="036">Hondarribia</option>
				<option value="042">Ibarra</option>
				<option value="043">Idiazabal</option>
				<option value="044">Ikaztegieta</option>
				<option value="045">Irun</option>
				<option value="046">Irura</option>
				<option value="047">Itsasondo</option>
				<option value="048">Larraul</option>
				<option value="902">Lasarte-Oria</option>
				<option value="049">Lazkao</option>
				<option value="050">Leaburu</option>
				<option value="051">Legazpi</option>
				<option value="052">Legorreta</option>
				<option value="068">Leintz-Gatzaga</option>
				<option value="053">Lezo</option>
				<option value="054">Lizartza</option>
				<option value="901">Mendaro</option>
				<option value="057">Mutiloa</option>
				<option value="056">Mutriku</option>
				<option value="063">Oiartzun</option>
				<option value="058">Olaberria</option>
				<option value="059">Oñati</option>
				<option value="076">Ordizia</option>
				<option value="905">Orendain</option>
				<option value="060">Orexa</option>
				<option value="061">Orio</option>
				<option value="062">Ormaiztegi</option>
				<option value="064">Pasaia</option>
				<option value="070">Segura</option>
				<option value="065">Soraluze-Placencia de las Armas</option>
				<option value="071">Tolosa</option>
				<option value="072">Urnieta</option>
				<option value="077">Urretxu</option>
				<option value="073">Usurbil</option>
				<option value="075">Villabona</option>
				<option value="078">Zaldibia</option>
				<option value="079">Zarautz</option>
				<option value="025">Zegama</option>
				<option value="026">Zerain</option>
				<option value="027">Zestoa</option>
				<option value="028">Zizurkil</option>
				<option value="081">Zumaia</option>
				<option value="080">Zumarraga</option></select><br />
				<input type="checkbox" value="b" name="eraikina">Eraikinaren izena(name etiketa gehitu)<br />
				<input type="submit" value="submit" name="submit">
			</form>	
			<p>Erabilerak:</p>
			<ul>
			<li>OSM-era B5M-n agertzen diren atariak inportatzeko.</li>
			<li>Eraikinen izenak etiketatzeko(exportatutako datuak txantiloi moduan erabiltzeko).</li>
			</ul>
			<p><a href="https://code.google.com/p/b5m2osm/">Google Code</a></p>
		<?php	
	}	
?>
</body>
</html>