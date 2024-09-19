<?php
session_start();
unset($_SESSION['chatHistory']); // Clear chat history
echo json_encode(['status' => 'Chat history cleared']);

