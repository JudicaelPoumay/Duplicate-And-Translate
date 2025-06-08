<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

body {
  font-family: Arial, sans-serif;
  margin: 20px;
  line-height: 1.6;
  background-color: #f9f9f9;
  color: #333;
}
.progress-log {
  background-color: #fff;
  border: 1px solid #ddd;
  padding: 15px;
  border-radius: 4px;
  min-height: 100px;
  max-height: 500px;
  overflow-y: auto;
}
.progress-log p {
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 1px dotted #eee;
}
.progress-log p:last-child {
  border-bottom: none;
}
.progress-log .success { color: green; }
.progress-log .error   { color: red; font-weight: bold; }
h1 { color: #555; }
.done a {
  display: inline-block;
  padding: 10px 15px;
  background-color: #0073aa;
  color: white;
  text-decoration: none;
  border-radius: 3px;
  margin-top: 15px;
}
.done a:hover { background-color: #005177; }

.spinner {
  border: 4px solid #f3f3f3;
  border-top: 4px solid #3498db;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  animation: spin 1s linear infinite;
  display: inline-block;
  margin-left: 10px;
  vertical-align: middle;
}

@keyframes spin {
  0%   { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.block-progress {
  margin-top: 10px;
  font-size: 0.9em;
}
