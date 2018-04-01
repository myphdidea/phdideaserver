<?php
$institutions="";
$sql="SELECT SUBSTR(institution_grid,6) AS grid, institution_name, institution_annuary_base, institution_emailsuffix
	FROM institution WHERE institution_isuniversity='1' ORDER BY institution_country";
$result=$conn->query($sql);
if($result->num_rows > 0)
	while($row=$result->fetch_assoc())
	{
//		if(strlen($row[$prefix.'_object']) > 18) $object=substr($row[$prefix.'_object'],0,18)."..."; else $object=$row[$prefix.'_object'];
		$institutions=$institutions.'<tr>
           		<td style="width: 65.1px;">'.$row['grid'].'<br>
           		</td>
           		<td style="width: 220px;">'.$row['institution_name'].'</a><br>
           		</td>
           		<td style="width: 150px; word-break: break-all"> <a href="index.php?page=redirect&link='.$row['institution_annuary_base'].'">'.$row['institution_annuary_base'].'</a><br>
           		</td>
           		<td style="width: 100px; word-break: break-all">'.$row['institution_emailsuffix'].'<br>
           		</td>
           	</tr>';
	}
?>
<div id="centerpage">
	
    <h2>Supported universities</h2>
    <div class="list">
    <?php
      	if(!empty($institutions))
       		echo '<table style="width: 99%>
       			<tbody class="table_override">
       				<tr style="background-color: white">
           				<th style="width: 65.1px;">GRID<br>
           				</th>
           				<th style="width: 220px;">Name<br>
           				</th>
           				<th style="width: 150px">Annuary<br>
           				</th>
           				<th style="width: 100px">e-mail suffix<br>
           				</th>
          			</tr>'.$institutions.'</tbody></table>';
		else echo '<i>No supported universities at the moment.</i>';
    ?>
    </div>
	
</div>
