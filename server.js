const express = require('express');
const puppeteer = require('puppeteer');
const sanitizeHtml = require('sanitize-html');
const validator = require('validator');
const helmet = require('helmet');

const app = express();
const PORT = 3000;

const whitelist = ['::1', '127.0.0.1', '::ffff:127.0.0.1', '82.55.177.110']; // Replace with your whitelisted IPs

app.use((req, res, next) => {
  console.log(`Request received from ${req.ip}`);
  if (!whitelist.includes(req.ip)) {
    return res.status(403).send('Not authorized');
  }
  next();
});

app.use(
  helmet.contentSecurityPolicy({
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'"],
      imgSrc: ["'self'", 'data:'],
      connectSrc: ["'self'"],
      fontSrc: ["'self'"],
      objectSrc: ["'none'"],
      mediaSrc: ["'self'"],
      frameSrc: ["'none'"],
    },
  })
);

app.get('/extract', async (req, res) => {
  const { url } = req.query;

  if (!url) {
    return res.status(400).send('URL parameter is required');
  }

  // Validate the URL
  if (!validator.isURL(url)) {
    return res.status(400).send('Invalid URL');
  }

  try {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    await page.goto(url, { waitUntil: 'domcontentloaded' });

    // Replace this with your specific logic to extract article content
    const articleContent = await page.$eval('article', (element) => element.innerHTML.trim());

    // Sanitize the article content
    const sanitizedArticleContent = sanitizeHtml(articleContent);

    await browser.close();

    // Send the sanitized article content as the response
    res.send(sanitizedArticleContent);
  } catch (error) {
    // Log the error for debugging purposes
    console.error(error);

    // Send a more specific error message based on the error that occurred
    if (error instanceof puppeteer.errors.TimeoutError) {
      res.status(500).send('Timeout error while loading the page');
    } else if (error.message.includes('failed to find element')) {
      res.status(404).send('Failed to find the article element on the page');
    } else {
      res.status(500).send('An unexpected error occurred');
    }
  }
});

app.listen(PORT, () => {
  console.log(`Server is running at http://localhost:${PORT}`);
});