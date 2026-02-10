<?php
// run.php
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Central de Notícias</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f6f8;
    padding: 40px;
}
.box {
    background: #fff;
    padding: 30px;
    max-width: 500px;
    margin: auto;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,.1);
}
button {
    padding: 15px 25px;
    font-size: 16px;
    background: #0073aa;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}
button:hover {
    background: #005f8d;
}
.log {
    margin-top: 20px;
    font-size: 13px;
    color: #555;
}
</style>
</head>

<body>
<div class="box">
    <h2>Atualizar notícias</h2>
    <p>Clique no botão abaixo para buscar, reformular e publicar as notícias.</p>

    <form method="post">
        <button name="rodar" value="1">Rodar automação</button>
    </form>

    <div class="log">
        <?php
        if (isset($_POST['rodar'])) {
            echo "<strong>Executando...</strong><br>";
            flush();

            require __DIR__ . '/pipeline.php';

            echo "<br><strong>Concluído.</strong>";
        }
        ?>
    </div>
</div>
</body>
</html>
