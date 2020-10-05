
window.onload = function() {
    document.getElementById("form").onsubmit = function(e){
        e.preventDefault();
        document.getElementById("button").disabled = true;
        document.getElementById("button").value = 'Запрос обрабатываеться, подождите...';
        // Заполняю данные
        let data = new FormData(document.getElementById("form"));
        // Отправляю данные
        let xhr = new XMLHttpRequest();
        xhr.open("POST", "handler.php");
        xhr.send(data);
        // Вывожу ответ
        xhr.onload = function() {
            alert(xhr.response);
            console.log(xhr.status);
            document.getElementById("button").disabled = false;
            document.getElementById("button").value = 'Проверить';
        }
    };
};
/* Альтернатива на Jquery + Ajax
$( document ).ready(function(){    
    $('form').submit(function (e) {
        e.preventDefault();
        $.ajax({
            url:    'handler.php', //url страницы (action_ajax_form.php)
            type:     "POST",
            dataType: "html",
            data: $("form").serialize(),
            success: function(response) {
                console.log(response);
            },
            error: function(response) {
                console.log(response);
            }
         });
    }); 
});
*/