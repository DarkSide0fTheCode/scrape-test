const express = require('express');
const puppeteer = require('puppeteer');

const app = express();
const PORT = 3000;

app.get('/extract', async (req, res) => {
  const { url } = req.query;

  if (!url) {
    return res.status(400).send('URL parameter is required');
  }

  try {
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    await page.goto(url, { waitUntil: 'domcontentloaded' });

    // Replace this with your specific logic to extract article content
    const articleContent = await page.$eval('article', (element) => element.innerHTML.trim());

    await browser.close();

    // Format the output
    const formattedResponse = {
      success: true,
      data: {
        url,
        articleContent,
      },
      message: 'Article extraction successful',
    };

    res.json(formattedResponse);
  } catch (error) {
    console.error(error);

    // Format the error response
    const errorResponse = {
      success: false,
      error: {
        message: 'Internal Server Error',
      },
    };

    res.status(500).json(errorResponse);
  }
});

app.listen(PORT, () => {
  console.log(`Server is running at http://localhost:${PORT}`);
});
