<?php
        Welcome to <i>myphdidea.org</i>! We are an online publishing platform
        founded with the purpose of connecting students at Master level with
        professors. For students, we hope to encourage a pro-active attitude
        towards graduate studies, where creativity and developing ideas of your own count
        more than career-minded pursuit of formal qualifications. For
        professors, we hope to give due credit to anyone dedicated to opening up
        science to new participants, on terms that are not basically one-sided.<br>
        <br>
        If you have got an idea for a PhD project, please become a member and
        upload it! You can then request reviews from professors; both your idea
        and the expert reviews will be given a permanent digital home on our
        site. Our crowd-sourced student editor system assures a minimum quality
        level for all uploads, and respectful treatment of professors' time.
        Though aimed particularly at students in engineering and
        natural science, anyone with 2 years of study and an institutional email
        account can register.<br>
        <br>
        If you are a first time visitor, the best point to start is the <a title="An introduction to the site"
          href="index.php?page=faq">FAQ</a> section. <br>
        <br>
        Recent site news:
        <div style="height: 200px;" id="toplist" class="list">
        <div style="text-align: center;"> <br>
          Number of verified student accounts: <?php echo $conn->query("SELECT 1 FROM student WHERE student_email_auth IS NOT NULL AND student_verdict_summary='1'")->num_rows; ?> </div>
      </div>