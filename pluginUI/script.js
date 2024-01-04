function applyChecksOnInput() {
  var inputs = document.querySelectorAll('input');

  inputs.forEach(function(input) {
      input.addEventListener('blur', function() {
          if (this.value === '') {
              // Input field is empty
              // Add your validation message or validation class here
              // this.classList.add('invalid');
          } else {
              // Input field is not empty
              // Remove the validation message or validation class here
              // this.classList.remove('invalid');
          }
      });
  });
}

function refreshList() {
  var listItems = document.querySelectorAll(".list-item");

  listItems.forEach(function(listItem) {
    if (listItem.classList.contains('active')) {
      listItem.classList.remove('active');
    }

    listItem.addEventListener('focusin', function() {
      console.log("focusin");
      this.classList.add('active');
    });

    listItem.addEventListener('focusout', function() {
      console.log("focusout");
      this.classList.remove('active');
    });
  });
}

function onConfirm(button) {
  var item = button.parentElement;
  if (button.classList.contains("confirmItem")) {
    console.log("active");
    
    var newItem = item.cloneNode(true);
    button.textContent = "Remove";
    refreshList();
    button.classList.remove("confirmItem");
    button.classList.add("removeItem");
    document.getElementById("list").appendChild(newItem);
  } else {
    console.log("not active");
    item.remove();
  }	
}

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM loaded with JavaScript");

  var listItems = document.querySelectorAll(".list-item");

  listItems.forEach(function(listItem) {
    listItem.addEventListener('focusin', function() {
      console.log("focusin");
      this.classList.add('active');
    });

    listItem.addEventListener('focusout', function() {
      console.log("focusout");
      this.classList.remove('active');
    });
  });
});
