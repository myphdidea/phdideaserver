<?php
session_start();
unset($_SESSION["user"]);
unset($_SESSION["isstudent"]);
unset($_SESSION['prof']);
unset($_SESSION['orcid']);
session_destroy();
header("Location: index.php")
?>