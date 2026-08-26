   <header class="topbar">

       <div class="page-title">

           <h1>
               Sales Dashboard
           </h1>

           <p>
               Orders, sales and customer service at a glance
           </p>

       </div>


       <div class="profile">

           <button type="button" class="profile-button" id="profileButton">

               <div class="avatar">
                   <?= e($avatar) ?>
               </div>

               <div class="profile-info">

                   <strong>
                       <?= e($fullName) ?>
                   </strong>

                   <small>
                       Sales Person
                   </small>

               </div>

               <i class="fa-solid fa-chevron-down" style="font-size:9px;color:#948a82"></i>

           </button>


           <div class="profile-menu" id="profileMenu">

               <div class="profile-menu-head">

                   <strong>
                       <?= e($fullName) ?>
                   </strong>

                   <small>
                       @<?= e($username) ?>
                       · Sales Person
                   </small>

               </div>


               <a href="orders.php">

                   <i class="fa-solid fa-cart-plus"></i>

                   Create New Order

               </a>


               <a href="my_orders.php">

                   <i class="fa-solid fa-receipt"></i>

                   My Orders

               </a>


               <button type="button" id="openPassword">

                   <i class="fa-solid fa-key"></i>

                   Reset Password

               </button>


               <a href="../handlers/logout.php">

                   <i class="fa-solid fa-right-from-bracket"></i>

                   Sign Out

               </a>

           </div>

       </div>

   </header>