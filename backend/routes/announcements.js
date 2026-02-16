const express = require('express');
const router = express.Router();
const announcementsController = require('../controllers/announcements');

router.get('/announcements', announcementsController.getAnnouncements);
router.get('/info', announcementsController.getInfo);
router.get('/event/status', announcementsController.getEventStatus);

module.exports = router;
