<?php
// 米日常按钮组件
?>
<div class="mirichang-wrapper">
  <a href="/SecWeb/expression/expression_emotes.php" class="mirichang-link" target="_blank">
    <div class="mirichang-counter">
      <div class="mirichang-container">
          <div class="mirichang-text">
              日常
          </div>
      </div>
    </div>
  </a>
</div>

<style>
.mirichang-wrapper {
    position: absolute;
    top: calc(50% + 70px); /* 衣柜下方，70px为间距，可根据实际调整 */
    left: 7.5%;
    z-index: 10;
    pointer-events: auto;
    height: auto;
    width: auto;
}
.mirichang-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
.mirichang-counter {
    position: relative;
}
.mirichang-container {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    background-color: rgba(204, 148, 113, 0.6);
    padding: 0.25vh 3vh;
    border-radius: 40px;
    color: white;
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
    border: none;
    transform: scale(1);
    cursor: pointer;
    position: relative;
    height: 60%;
    box-sizing: border-box;
}
.mirichang-container:hover {
    background-color: rgba(204, 148, 113, 0.8);
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}
.mirichang-text {
    font-family: 'QiantuHouhei', sans-serif;
    font-size: calc(12px + 1vh);
    line-height: 1.5;
    text-align: center;
    letter-spacing: 2px;
    font-weight: 600;
}
@media (max-width: 768px) {
    .mirichang-container {
        padding: 0.25vh 2vh;
    }
    .mirichang-text {
        font-size: calc(10px + 1vh);
        letter-spacing: 1px;
    }
}
</style> 