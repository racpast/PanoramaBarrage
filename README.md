# 全景留言墙

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://www.php.net/)
[![Author](https://img.shields.io/badge/Author-Racpast-orange.svg)](https://github.com/racpast)

 一个基于 Three.js 和原生PHP构建的，拥有3D全景背景的交互式弹幕留言墙项目。用户可以注册登录，在沉浸式的全景空间中发送自定义样式和动画的弹幕，创造一个独特而有趣的在线交流体验。

## 🚀 主要功能

* **3D全景背景**：基于 `Three.js` 和 `tpanorama.js` 实现，支持鼠标拖拽和滚轮缩放，提供沉浸式视觉体验。
* **实时弹幕系统**：
  * 用户可以发送弹幕，并实时显示在所有访客的屏幕上。
  * 支持自定义弹幕**文字颜色**和**背景颜色**。
  * 支持三种弹幕模式：**向右滚动**、**向左滚动**和**居中静止**。
  * 支持五档弹幕速度调节。
* **完整的用户系统**：
  * 支持用户**注册**，并通过邮件验证激活账户。
  * 支持用户**登录**与**登出**。
  * 支持用户**上传或更换个人头像**。
  * 支持用户**修改密码**。
  * 支持**忘记密码**流程，通过邮箱接收重置链接。
* **交互与管理**：
  * 支持对弹幕进行**右键举报**，达到阈值后邮件通知管理员。
  * 支持一键**隐藏或显示**所有弹幕。
  * 所有核心操作均有美观的弹窗和动态效果。


## 🛠️ 技术栈

* **后端**:
  * PHP 8.0+
  * MySQL / MariaDB
  * PHPMailer（通过 Composer 管理）
* **前端**:
  * HTML5 / CSS3
  * JavaScript (ES6+)
  * Three.js
  * tpanorama.js
  * Pickr
  * Font Awesome


## 📂 项目结构

```
.
│  composer.json
│  composer.lock
│  config.php.example
│  index.html
│  README.md
│
├─api/
│   └─ （所有后端PHP接口文件）
│
├─css/
│  ├─pickr/
│  └─ main.css
│
├─img/
│   └─ （图片资源, 包括 favicon.svg）
│
├─js/
│  ├─pickr/
│  ├─three/
│  ├─tpanorama/
│  └─ app.js
│
├─sql/
│   └─ schema.sql
│
└─vendor/
    └─ （Composer 依赖）
```


## ⚙️ 安装与部署指南

1. **克隆仓库**

   ```bash
   git clone https://github.com/racpast/PanoramaBarrage.git
   cd PanoramaBarrage
   ```

2. **安装依赖**
   确保您已安装 [Composer](https://getcomposer.org/)，然后运行：

   ```bash
   composer install
   ```

   这将会安装 PHPMailer 等PHP依赖到 `vendor` 目录。

3. **数据库设置**

   * 在您的 MySQL 服务器中创建一个新的数据库（例如，`panorama_barrage`）。
   * 选择这个新创建的数据库。
   * 导入项目中的 `sql/schema.sql` 文件来创建所有需要的表。

4. **配置项目**

   * 将根目录下的 `config.php.example` 复制一份，并重命名为 `config.php`。
   * 打开 `config.php` 文件，根据注释填写真实的配置信息。**重点关注**以下部分：
     * **数据库配置**: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`。
     * **邮件发送配置**: `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS` 等。
     * **网站核心配置**: `BASE_URL` 必须是您网站的公开访问域名。
     * **文件上传配置**: 确认 `UPLOADS_PATH_AVATARS` 指向的服务器物理路径正确无误。

5. **Web服务器配置**

   * 将您的 Web 服务器（如 Nginx 或 Apache）的网站根目录指向本项目的**根目录**。
   * 确保您在 `config.php` 中 `UPLOADS_PATH_AVATARS` 常量所指定的目录**存在**，并且 Web 服务器（例如 `www-data` 用户）对其有**写入权限**。如果目录不存在，您需要手动创建它。

6. **完成！**
   现在，通过您配置的 `BASE_URL` 访问网站，开始体验吧！


## 📄 开源许可

本项目基于 [MIT License](https://opensource.org/licenses/MIT) 开源。


## 🙏 致谢

* 感谢所有使用到的开源库的作者们。
